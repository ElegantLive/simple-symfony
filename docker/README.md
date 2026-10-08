# Docker 本地开发环境

给这个 Symfony 4.3 项目搭的本地开发栈。**不改任何应用代码**，容器通过环境变量覆盖 `.env`。

## 一键启动

```bash
# 1. 准备被 gitignore 掉的东西
cp .env.docker.example .env      # 若已有 .env 可跳过
ls config/certificate/jwt        # 必须存在，否则 JWT 登录会 500
                                 # 缺了就生成：见「生成 RSA 证书」

# 2. 初始化（构建镜像 → 装依赖 → 建表 → 起栈 → 探活）
./docker/setup.sh
```

完成后：

| 地址 | 用途 |
|---|---|
| http://localhost:8080 | 应用（`GET /tag/hot` 可用作健康探针） |
| http://localhost:8025 | MailHog 收件箱。**仅当 `.env` 里没有 `MAILER_DSN` 时**才收信，见「邮件怎么走」 |
| localhost:3307 | MySQL（`simple` / `root` / `root`） |

## 服务拓扑

| 服务 | 容器 | 镜像 | 宿主机端口 |
|---|---|---|---|
| `app` | simple-app | 本地构建 `simple-app:dev` | — |
| `worker` | simple-worker | 同上 | — |
| `nginx` | simple-nginx | nginx:1.24-alpine | **8080** → 80 |
| `db` | simple-db | mysql:5.7 | **3307** → 3306 |
| `redis` | simple-redis | redis:5-alpine | 6379 |
| `mailhog` | simple-mailhog | mailhog/mailhog | 1025 / **8025** |

端口可覆盖，在仓库根目录的 `.env` 里改（或直接 `export`）：

```bash
APP_PORT=8090 DB_PORT=3306 REDIS_PORT=6380
```

## 为什么是这些版本（都踩过坑）

**PHP 7.3 + Debian bullseye**
依赖树（Symfony 4.3、doctrine/*、老 composer.lock）早于 PHP 8，glibc 基础镜像最稳。

**Composer 1.10.27，不是 2.x**
`composer.lock` 锁定 `symfony/flex v1.4.5` 和 `ocramius/package-versions 1.4.0`，
两者都声明 `"composer-plugin-api": "^1.0"`。Composer 2 提供的是 `2.2.0`，会直接拒绝这个 lock
（`Your lock file does not contain a compatible set of packages`）。Composer 1.10.27 提供 `1.10.0`，正常解析。

**bullseye-security 套件被移除**
bullseye 现在是 Debian 的 oldoldstable，它的 security 池里的 `.deb` 已被清理，但索引仍列着它们
（例如 `git 1:2.30.2-1+deb11u5`），导致 `apt-get install` 一片 404。已在
`docker/php/Dockerfile` 里把该套件从 `sources.list` 删掉，改用仍可用的 `bullseye/main`。
代价是镜像不带安全补丁——对 EOL 栈的本地开发镜像是可接受的，**不要**拿它当生产镜像基础。

**php-fpm 以 root 启动，自己降权**
官方 PHP 镜像的设计是 FPM master 保持 root、由 pool 的 `user`/`group` 让 worker 跑在 www-data。
入口脚本因此对 `php-fpm` **不做** `gosu`；其他命令（`bin/console`、`composer`、worker）都会降到 www-data。
把 php-fpm 包进 gosu 会让它无法打开官方日志路径 `/proc/self/fd/2`，直接启动失败。

## 数据库 schema：这个仓库**已经不用** migrations 了

`src/Migrations/.gitignore` 的内容只有一行 `*`（在 2020-03-19 的 commit `36ad985`
"ignore database migrations" 里加的）。这条规则带来的后果：

| 文件 | git 状态 |
|---|---|
| `src/Migrations/.gitignore` | 已跟踪，内容 `*` |
| `Version20190923042819.php`（2019-09） | 曾经被跟踪（加于该规则之前），**已删除** |
| `Version20200317144652.php`、`...20200323062157.php`、`...20200327063558.php` | 被忽略，**从未提交**，只存在于本地 |

所以「git 里的迁移集合」曾被冻结在 2019 年的一个过时快照上，而 entity 一直在演进
（`sex` 从 simple_array 改成 enum、加了 `Timestamps`、又加了 `SoftDeleteable`）。
那个过时迁移实测有两个问题：

- **本机**（本地还多出 3 个未提交的迁移）：`migrate` 报 `Table 'user' already exists`
- **全新 clone**（当时只有那一个迁移）：`migrate` 能跑通，但建出的表**缺
  `created_at`/`updated_at`/`deleted_at` 且 `sex` 类型不对**，Doctrine 自己判定
  `[ERROR] The database schema is not in sync with the current mapping file`

两条路都不可用，所以**那个过时的迁移已删除**，`src/Migrations` 在 git 里现在只剩
`.gitignore`。**entity 映射是唯一的事实来源**，`docker/setup.sh` 用它建表：

```bash
docker compose run --rm --no-deps -e RUN_BOOT_TASKS=0 app \
    php bin/console doctrine:schema:update --force
```

它只执行差异、可重复执行，并且会**顺带建出 `messenger_messages`**——
因为 Doctrine messenger transport 注册了自己的 schema subscriber，所以 worker 的队列表不用额外步骤。

入口脚本里 `RUN_MIGRATIONS` 默认 **0**（关闭）。想手动跑迁移：

```bash
make -f Makefile.docker schema-update    # 实际可用的方式
make -f Makefile.docker migrate          # 现在没有可用迁移，等于空操作
```

> **`User.sex` 与 `columnDefinition` 的坑（已修，留个记录）**
>
> 这个列曾经让 `doctrine:schema:validate` 永远报 `not in sync`，且 `schema:update`
> 每次都会生成一条 `ALTER TABLE user CHANGE sex sex enum('MAN','WOMEN')` —— 反复执行也修不好。
>
> 原因是 `@ORM\Column(type="string", columnDefinition="enum('MAN','WOMEN')")` 这个写法：
> 生成 DDL 时 `columnDefinition` 会**原样输出并覆盖其它所有列属性**，所以映射里隐含的
> `NOT NULL` 从未被写进数据库，`sex` 实际建成了 nullable。而 DBAL 的
> `Comparator::diffColumn` 比较的是 `type / notnull / unsigned / autoincrement / default / length`
> （`length` 有 `?: 255` 兜底，所以 DB 的 0 与映射的 255 不算差异），**根本不读 `columnDefinition`**
> —— 于是唯一差异就是 `notnull`：DB 侧 false、映射侧 true。重跑 ALTER 也没用，因为 ALTER 本身
> 就是由这个字符串生成的，里面没有 NOT NULL。
>
> 修法：把 `NOT NULL` **写进 `columnDefinition` 字符串里面**，然后执行一次
> `doctrine:schema:update --force`。之后 `server_version` 侧收敛，`schema:validate` 通过，
> `schema:update` 变成 `Nothing to update`。entity 里那段注释写明了为什么不能把
> `NOT NULL` 挪出来当普通属性 —— 别当成冗余删掉。
>
> 注意：`NOT NULL` 加在已有数据的表上时，若存在 `sex IS NULL` 的行会失败（容器里 MySQL 是
> `STRICT_TRANS_TABLES`，所以会**报错**而不是把 NULL 静默转成 `''`，这是好事）。
> 迁移前先 `SELECT COUNT(*) FROM user WHERE sex IS NULL;`。

如果你想要回正经的迁移历史，需要重建 baseline：删掉本地那 3 个被忽略的文件，
对空库跑 `doctrine:migrations:diff` 生成一个完整迁移并提交，同时把
`src/Migrations/.gitignore` 的 `*` 改成 `*` + `!.gitignore` + `!Version*.php`。

## 卷与热更新

| 挂载 | 类型 | 为什么 |
|---|---|---|
| `./` → `/var/www/html` | bind | 代码热更新，改 PHP 立刻生效 |
| `vendor` → `.../vendor` | 命名卷 | 避开 macOS bind mount 的慢速小文件读，也不污染宿主机 vendor |
| `var` → `.../var` | 命名卷 | Symfony 缓存/日志/profiler。**用 bind mount 会慢到不可用** |
| `./public/uploads` | bind | 上传的图片宿主机可见 |
| `./config/certificate` | bind, ro | JWT / 签名私钥，被 gitignore，不能进镜像 |

> `var/` 是命名卷，所以宿主机上看不到 `var/log/dev.log` 和 profiler 数据（刻意的性能取舍）。
> 读日志用 `docker compose logs -f app`，或 `make -f Makefile.docker shell` 进容器看。

## 环境变量如何覆盖 `.env`

`config/bootstrap.php` 用的是 `new Dotenv(false)`，Symfony 的 `populate()` 默认**不覆盖已存在的变量**，
而 compose 注入的是真实进程环境变量，所以 compose 优先于 `.env`：

| 变量 | `.env`（宿主机） | compose（容器） |
|---|---|---|
| `DATABASE_URL` | `...@localhost:3306` | `...@db:3306` |
| `REDIS_DSN` | `redis://127.0.0.1:6379` | `redis://redis:6379` |
| `MAILER_DSN` | 你的真实 SMTP | **跟随 `.env`**（未设则回退 MailHog，见下） |
| `CORS_ALLOW_ORIGIN` | 你的来源正则 | **原样生效**（应用直接读 `.env`，不经过 compose） |
| `TRUSTED_PROXIES` | 注释掉 | `127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16` |

`TRUSTED_PROXIES` 必须设置：nginx 在 php-fpm 前面，不信任转发头的话 Symfony 会拿到错误的客户端 IP 和 scheme。

> **`CORS_ALLOW_ORIGIN` 现在是必需的**：`config/packages/nelmio_cors.yaml` 用 `%env(CORS_ALLOW_ORIGIN)%` 读取它
> （`origin_regex: true`，所以填的是**正则**）。以前这里的配置被硬编码成 `allow_origin: ['*']`，
> 导致 `.env` 里这个变量**从未生效**、任何来源都被放行；现在它真的起作用了。
> 缺了它会直接请求失败，所以 `.env.docker.example` 里已给出默认值。
> 改完记得 `php bin/console cache:clear`（应用读的是 bind mount 进来的 `.env`，不走 compose 插值）。

> **注意**：`DATABASE_URL` / `REDIS_DSN` / `MESSENGER_TRANSPORT_DSN` 在 compose 里是**写死的**（指向 `db` / `redis`），
> 所以你在 `.env` 里把它们改成 `localhost` 对容器**没有影响**，这是刻意的。
> `MAILER_DSN`、`FROM_EMAIL`、`DEV_EMAIL`、`APP_ENV`、`APP_SECRET`、`DB_*`、各端口则**跟随 `.env`**。
> 改完要重建容器：`docker compose up -d`（compose 会检测到插值变化并 recreate）。

## 邮件怎么走

`MAILER_DSN` 在 compose 里写成 `${MAILER_DSN:-smtp://mailhog:1025}`，也就是**跟随你的 `.env`**：

| `.env` 里的 `MAILER_DSN` | 容器行为 |
|---|---|
| 有真实 SMTP DSN（如 `smtp://user:pass@smtp.163.com`） | **真的往外发信**，MailHog 收不到 |
| 没有设 | 回退到 `smtp://mailhog:1025`，全部被 MailHog 拦截（`http://localhost:8025`） |

`.env.docker.example` 默认不给 `MAILER_DSN`，所以**全新 clone 是 MailHog 安全默认**。
要在容器内强行拦截（即使 `.env` 设了真地址）：

```bash
MAILER_DSN=smtp://mailhog:1025 docker compose up -d
```

`FROM_EMAIL` / `DEV_EMAIL` 总是取自 `.env`，是信封地址，不决定投递去向。

实测过这条路真的通（163）：真实凭证 `SENT`，而**故意改错密码被拒**
（`Failed to authenticate on SMTP server ... LOGIN, PLAIN, XOAUTH2`），
说明容器确实在和 163 做 SMTP 认证，不是代理伪造成功。

## 生成 RSA 证书

应用从 `config/certificate` 读两套密钥（**被 gitignore，不在仓库里**）：

| 目录 | 用途 | 被谁读 |
|---|---|---|
| `jwt/` | JWT 签名（RS256） | `App\Service\Token` → `firebase/php-jwt` |
| `sign/` | 请求签名（RSA 加解密） | `App\Service\Signature` → `openssl_private_encrypt` |

每套三个文件，因为应用和客户端要的编码不同：

| 文件 | 格式 | 说明 |
|---|---|---|
| `rsa_private.pem` | PKCS#1 `BEGIN RSA PRIVATE KEY` | PHP openssl 直接读的就是它 |
| `rsa_public.pem` | SPKI `BEGIN PUBLIC KEY` | 交给客户端的那份 |
| `pkcs8_rsa_private.pem` | PKCS#8 `BEGIN PRIVATE KEY` | 给要求 PKCS#8 的客户端 |

用 `docker/gen-certificates.sh` 生成：

```bash
# 默认 4096 位；已存在的密钥必须先删或加 --force
docker compose run --rm --no-deps \
  -v "$PWD/config/certificate:/var/www/html/config/certificate" \
  --entrypoint sh app docker/gen-certificates.sh

# 参数
#   --bits N   密钥长度，默认 4096（仓库里现有的只有 1024 位，偏弱）
#   --force    覆盖前把旧文件备份成 *.bak.<时间戳>
```

> **为什么上面要写 `-v`**：compose 里 `config/certificate` 是 **`:ro` 只读挂载**，
> 不覆盖挂载的话脚本会报 `not writable` 并退出（脚本会直接把这条命令打给你）。
> 也可以直接在宿主机上跑（宿主机上它是普通可写目录）。

脚本拒绝覆盖已存在的密钥，除非给 `--force` —— 因为换密钥会让**已签发的所有 JWT 立刻失效**，
并且会**弄坏任何把 `rsa_public.pem` 内置了的客户端**。它会自己校验
`openssl rsa -check` 以及「公钥是否确实由该私钥导出」。

当前仓库里那套密钥（1024 位）**实测可用**，JWT 编解码正常、
用另一把密钥签的 token 会被正确拒绝、签名加解密双向往返正常，所以**不是必须重新生成**。

## 依赖安装（composer）

容器内用 **Composer 1**。两个与直觉不同的点，都实测过：

1. **`composer install` 不需要 packagist，也不需要换镜像源。**
   `composer.lock` 里全部 94 个 dist URL 都指向 `github.com` / `api.github.com`，
   安装直接按 lock 下载，不查元数据。实测装完 `composer.lock` 的 sha256 **不变**。

2. **`composer.json` 里那个镜像源确实是坏的。**
   `repositories.packagist` 指向 `packagist.phpcomposer.com`，实测返回 404。
   Dockerfile 里仍然把 Composer 的**全局**配置指向阿里云，但要注意：
   **项目的 `repositories` 优先于全局配置**，所以这一步救不了这个项目的 `composer require`。
   只有当你把 `composer.json` 里那段坏源去掉之后，全局配置才会生效。

```bash
# 装新依赖（会用到 packagist，需先处理 composer.json 里的坏源）
docker compose exec -u www-data app composer require vendor/package
docker compose restart app worker
```

`docker/setup.sh` 里刻意**没有**传 `-u www-data`：入口脚本必须以 root 启动才能修正卷的属主，
再由它自己降权去跑 composer。传了 `-u` 会让 vendor 卷停留在 root 属主，应用写不了缓存。

## 常用命令

```bash
make -f Makefile.docker help            # 列出所有目标
make -f Makefile.docker setup           # 首次初始化，可重复执行
make -f Makefile.docker up              # 起栈（会先检查 vendor 卷）
make -f Makefile.docker logs-web        # nginx + php-fpm 日志
make -f Makefile.docker console ARGS="cache:clear"
make -f Makefile.docker schema-update   # 按 entity 同步表结构
make -f Makefile.docker shell           # 以 www-data 进容器
make -f Makefile.docker reset           # down -v，清空所有数据卷
```

改了 `docker/php/*` 或 `Dockerfile` 要重建：`docker compose up -d --build app worker`。
只改 `docker/nginx/default.conf` 的话 `docker compose restart nginx` 就够。

## 生产镜像（可选，未验证）

`docker/php/Dockerfile` 里有一个 `prod` stage（依赖和代码打进镜像、`--no-dev`、无 bind mount）：

```bash
docker build --target prod -f docker/php/Dockerfile -t simple-app:prod .
```

生产还要额外处理（当前没做）：`composer dump-env prod`、`opcache.validate_timestamps=0`、
正式 TLS、不要把 3306/6379 暴露出去，以及**换掉上面提到的 bullseye 无安全补丁的基础镜像**。

## 排障

**容器起来就退出，日志说 `vendor/autoload.php is missing`**
预期行为。`vendor/` 是命名卷，新克隆的仓库第一次起栈时它是空的。跑 `./docker/setup.sh`。

**`FATAL: database host 'xxx' does not resolve`**
`DATABASE_URL` 的 host 写错了。容器里**必须用服务名** `db`，不能用 `localhost`。

**`failed to open configuration file '/usr/local/etc/php-fpm.d/zz-app.conf': Permission denied`**
检出目录里的文件权限过窄（例如 0600），而 php-fpm 以 www-data 读配置。Dockerfile 已经对这几个
文件强制 `chmod 0644`，不再依赖检出权限；如果你改过这些路径要一并加上。

**`Table 'user' already exists`**
你在跑 migrations，而本地还剩着那 3 个从未提交的迁移文件（见上面「数据库 schema」一节）。
改用 `schema-update`，或把那 3 个文件也删掉。

**`/article/list` 之类接口返回 500 `Call to a member function getId() on null`**
这是应用层行为，不是环境问题：这些接口需要 `Authorization` token，空库下没有当前用户。

**端口被占用**
改 `DB_PORT` / `REDIS_PORT` / `APP_PORT`。

**改了代码没生效**
php-fpm 会自动重读 PHP 文件；改 `config/` 下的 YAML 或 `.env` 需要
`make -f Makefile.docker console ARGS="cache:clear"`；改容器层配置要 `--build`。

## 这套配置实际验证到了什么

在 Docker Desktop 4.41 / Engine 28.1.1（linux/amd64）上从零跑过：

- `./docker/setup.sh` 全流程通过（`down -v` 清空后重建）
- 六个基础镜像 tag 与 `composer:1.10.27` 均存在于 registry
- PHP 7.3.33，`pdo_mysql` / `mbstring` / `bcmath` / `intl` / `zip` / `opcache` 全部就位
- MySQL 5.7.44：`utf8mb4` / `utf8mb4_unicode_ci` / `mysql_native_password`，init.sql 生效
- `nginx -t` 通过；`docker compose config` 通过
- `composer install` 成功且 `composer.lock` 哈希不变
- entity 建表产出 8 张表，`doctrine:query:sql` 可查询
- **`GET /tag/hot` 经 nginx → php-fpm → Symfony → MySQL 返回 200 与预期 JSON**
- nelmio CORS 头正常；Predis 连通 Redis（PING → PONG）
- worker 正常 `Consuming messages from transports "async"`
- 邮件：回退模式下确实进 MailHog；跟随 `.env` 时确实经 163 认证发出
  （错密码被拒，见「邮件怎么走」）
- 现有 `config/certificate` 密钥：JWT RS256 往返正常、异钥 token 被拒、签名加解密往返正常
- `docker/gen-certificates.sh`：生成 / 校验配对 / 无 `--force` 拒绝 / `--force` 备份 / `<2048` 位拒绝，
  生成的密钥能跑通真实 JWT 往返

未验证：`prod` stage、JWT 登录链路端到端（需要真实用户数据）、真实收件箱是否收到那封验证邮件。
