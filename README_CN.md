# simple-symfony-blog

这是一个使用 `symfony@4.3` 构建简单的博客api。

如果你是刚入门的话，可能会帮到你，也欢迎各位到 `issue` 提问交流。

主要实现了以下功能：

 - 身份校验 - 登录注册等
 - 自定义错误处理
 - 自定义验证器
 - 异步发送邮件
 - `cors` 跨域
 - 文件上传
 - 软删除
 - 自定义 `make:rule`

部分 `api` 示例：
 
- `GET /article/list` 文章分页
- `GET /article/list/self` 文章分页
- `GET /article/{id}` 文章详情
- `POST /article/` 发表文章
- `PUT /article/{id}` 修改文章 
- `DELETE /article/{id}` 撤下文章 
- `POST /article/{id}/comment/` 文章评论
- `POST /article/{id}/comment/{id}/reply/{id}` 回复评论
- ...查看更多 `bin/console debug:router`

## License

[MIT license](http://opensource.org/licenses/MIT)