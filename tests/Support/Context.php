<?php

namespace App\Tests\Support;

/**
 * Shared fixtures and configuration for the functional tests.
 *
 * Everything the suite needs is created through the public API, never by writing
 * to the database directly. That is deliberate: seeding with INSERTs would skip
 * the registration flow, the password hashing and the verification codes, and
 * would mean this file has to be kept in step with the schema by hand.
 *
 * Because of that, the suite expects a database that has just been created. Two
 * reasons:
 *
 *   - users, articles, comments and replies cannot be reset through the API, so a
 *     second run against the same data would hit uniqueness errors;
 *   - GET /user/info/rand picks a random id between min(id) and max(id) and
 *     dereferences the result, so a table with gaps (from soft deletes) can
 *     return a missing row and fail. That is a property of the endpoint, not of
 *     this suite; see the report.
 *
 * Locally:
 *   docker compose exec -T db mysql -uroot -proot -e "DROP DATABASE simple_test; CREATE DATABASE simple_test"
 *   docker compose exec -T -e APP_ENV=test -e DATABASE_URL=mysql://root:root@db:3306/simple_test -u www-data \
 *     app php bin/console doctrine:schema:create
 */
final class Context
{
    /** @var string|null */
    private static $baseUrl;

    /** @var string|null */
    private static $signKey;

    /** @var string|null */
    private static $mailHogUrl;

    /** @var string */
    private static $runId;

    public static $ownerMobile   = '13800000001';
    public static $ownerName     = 'smoke-owner';
    public static $ownerEmail    = 'smoke-owner@example.test';
    public static $ownerPassword = 'SmokePass1';
    public static $ownerId;
    public static $ownerToken;

    public static $otherMobile   = '13800000002';
    public static $otherName     = 'smoke-other';
    public static $otherEmail    = 'smoke-other@example.test';
    public static $otherPassword = 'SmokePass1';
    public static $otherId;
    public static $otherToken;

    public static $doomedMobile   = '13800000003';
    public static $doomedName     = 'smoke-doomed';
    public static $doomedEmail    = 'smoke-doomed@example.test';
    public static $doomedPassword = 'SmokePass1';
    public static $doomedId;
    public static $doomedToken;

    public static $articleId;
    public static $commentId;
    public static $replyId;
    public static $tagId;
    public static $avatarId;

    public static $freeArticleId;
    public static $thrownArticleId;
    public static $doomedArticleId;
    public static $doomedCommentId;
    public static $doomedReplyId;

    /** @var string */
    public static $codeEmail;

    /** @var string */
    public static $pngFile;

    /** @var string */
    public static $fixtureArticleTitle;

    /** @var string */
    public static $doomedArticleTitle;

    /** @var string */
    public static $freeArticleTitle;

    /** @var string */
    public static $thrownArticleTitle;

    /** @var bool */
    private static $booted = false;

    // ------------------------------------------------------------------ config

    public static function configured(): bool
    {
        return (string) getenv('SMOKE_BASE_URL') !== '';
    }

    /**
     * Why the functional tests cannot run, for the skip message.
     */
    public static function notConfiguredReason(): string
    {
        return 'SMOKE_BASE_URL is not set, so there is no running application to test. '
            . 'Start one and point the suite at it, for example: '
            . 'APP_ENV=test php -S 127.0.0.1:8000 -t public public/index.php, '
            . 'then SMOKE_BASE_URL=http://127.0.0.1:8000. '
            . 'See .github/workflows/ci.yml for how CI does it.';
    }

    private static function configure(): void
    {
        if (self::$baseUrl !== null) {
            return;
        }

        $signKey = getenv('SMOKE_SIGN_KEY');
        $mail    = getenv('SMOKE_MAILHOG_URL');
        $runId   = getenv('SMOKE_RUN_ID');

        self::$baseUrl    = rtrim((string) getenv('SMOKE_BASE_URL'), '/');
        self::$signKey    = $signKey !== false && $signKey !== ''
            ? $signKey
            : dirname(__DIR__, 2) . '/config/certificate/sign/rsa_public.pem';
        self::$mailHogUrl = $mail !== false && $mail !== '' ? rtrim($mail, '/') : 'http://127.0.0.1:8025';
        self::$runId      = $runId !== false && $runId !== '' ? $runId : (string) time();
    }

    public static function signKey(): string
    {
        self::configure();

        return self::$signKey;
    }

    public static function mailHogUrl(): string
    {
        self::configure();

        return self::$mailHogUrl;
    }

    public static function runId(): string
    {
        self::configure();

        return self::$runId;
    }

    // ----------------------------------------------------------------- clients

    public static function anonymous(): ApiClient
    {
        self::configure();

        return new ApiClient(self::$baseUrl, self::$signKey);
    }

    public static function asOwner(): ApiClient
    {
        return self::anonymous()->withToken((string) self::$ownerToken);
    }

    public static function asOther(): ApiClient
    {
        return self::anonymous()->withToken((string) self::$otherToken);
    }

    public static function asDoomed(): ApiClient
    {
        return self::anonymous()->withToken((string) self::$doomedToken);
    }

    /**
     * The client a probe asked for by name.
     */
    public static function clientFor($as): ApiClient
    {
        if ($as === 'owner') {
            return self::asOwner();
        }
        if ($as === 'other') {
            return self::asOther();
        }
        if ($as === 'doomed') {
            return self::asDoomed();
        }

        return self::anonymous();
    }

    // ------------------------------------------------------------- placeholders

    /**
     * Replace {placeholders} in a probe's path, query, body or file list.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function substitute($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = self::substitute($item);
            }

            return $out;
        }

        if (!is_string($value)) {
            return $value;
        }

        return strtr($value, [
            '{owner}'         => (string) self::$ownerId,
            '{other}'         => (string) self::$otherId,
            '{mobile}'        => self::$ownerMobile,
            '{password}'      => self::$ownerPassword,
            '{article}'       => (string) self::$articleId,
            '{freeArticle}'   => (string) self::$freeArticleId,
            '{thrownArticle}' => (string) self::$thrownArticleId,
            '{comment}'       => (string) self::$commentId,
            '{reply}'         => (string) self::$replyId,
            '{tag}'           => (string) self::$tagId,
            '{avatar}'        => (string) self::$avatarId,
            '{doomedArticle}' => (string) self::$doomedArticleId,
            '{doomedComment}' => (string) self::$doomedCommentId,
            '{doomedReply}'   => (string) self::$doomedReplyId,
            '{codeEmail}'     => self::$codeEmail,
            '{pngFile}'       => self::$pngFile,
        ]);
    }

    // ---------------------------------------------------------------- bootstrap

    /**
     * Create the fixtures once per process. Safe to call from every test class.
     */
    public static function bootstrap(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        self::configure();

        self::$runId      = self::$runId . '-' . substr((string) getmypid(), 0, 4);
        self::$codeEmail  = 'smoke-code-' . self::$runId . '@example.test';
        self::$pngFile    = self::writePng();
        self::$ownerToken = '';

        self::applyRunIdentities();

        $owner = self::createUser('owner', self::$ownerMobile, self::$ownerPassword, self::$ownerName, self::$ownerEmail);
        self::$ownerId    = $owner[0];
        self::$ownerToken = $owner[1];

        $other = self::createUser('other', self::$otherMobile, self::$otherPassword, self::$otherName, self::$otherEmail);
        self::$otherId    = $other[0];
        self::$otherToken = $other[1];

        $doomed = self::createUser('doomed', self::$doomedMobile, self::$doomedPassword, self::$doomedName, self::$doomedEmail);
        self::$doomedId    = $doomed[0];
        self::$doomedToken = $doomed[1];

        $owner = self::asOwner();
        $aid   = self::$articleId;

        // A fixture article, found by title rather than by position: createdAt has
        // one-second resolution, so two articles created in the same second would
        // come back in an undefined order.
        self::must(
            $owner->post('/article/', [
                'title'       => self::$fixtureArticleTitle,
                'content'     => 'fixture article content',
                'description' => 'fixture article description',
                'tag'         => ['smoke'],
            ]),
            'create the fixture article',
            [200]
        );
        self::$articleId = self::findInList(
            $owner->get('/article/list/self'),
            'title',
            self::$fixtureArticleTitle,
            'the fixture article'
        );

        self::must(
            $owner->post('/article/', [
                'title'       => self::$doomedArticleTitle,
                'content'     => 'doomed article content',
                'description' => 'doomed article description',
                'tag'         => ['smoke'],
            ]),
            'create the throwaway article',
            [200]
        );
        self::$doomedArticleId = self::findInList(
            $owner->get('/article/list/self'),
            'title',
            self::$doomedArticleTitle,
            'the throwaway article'
        );

        // Two more articles, each with exactly one job. The destructive probes
        // cannot share an object: DELETE /article/{id} soft-deletes the article,
        // and a later DELETE of a comment on a deleted article answers 410, while
        // POST /article/{id}/comment/ needs an article that has no comment yet -
        // Comment::$article is @ORM\OneToOne, so a second comment is a constraint
        // violation, not a 400.
        self::must(
            $owner->post('/article/', [
                'title'       => self::$freeArticleTitle,
                'content'     => 'free article content',
                'description' => 'free article description',
                'tag'         => ['smoke'],
            ]),
            'create the article kept free of comments',
            [200]
        );
        self::$freeArticleId = self::findInList(
            $owner->get('/article/list/self'),
            'title',
            self::$freeArticleTitle,
            'the comment-free article'
        );

        self::must(
            $owner->post('/article/', [
                'title'       => self::$thrownArticleTitle,
                'content'     => 'thrown article content',
                'description' => 'thrown article description',
                'tag'         => ['smoke'],
            ]),
            'create the article to be deleted',
            [200]
        );
        self::$thrownArticleId = self::findInList(
            $owner->get('/article/list/self'),
            'title',
            self::$thrownArticleTitle,
            'the article to be deleted'
        );

        $aid = self::$articleId;

        // Comments. deleteArticleComment insists the caller is both the commenter
        // and the article's author, so every comment here is by the owner on the
        // owner's own article.
        //
        // The throwaway comment goes on the throwaway article rather than on the
        // fixture one: Comment::$article is mapped @ORM\OneToOne, so the schema
        // carries UNIQUE(article_id) and an article can hold exactly one comment.
        // See the report - that constraint is almost certainly not intended.
        self::must(
            $owner->post('/article/' . $aid . '/comment/', ['content' => 'fixture comment']),
            'create the fixture comment',
            [200]
        );
        self::$commentId = self::findInList(
            $owner->get('/article/' . $aid . '/comment/'),
            'content',
            'fixture comment',
            'the fixture comment'
        );

        self::must(
            $owner->post('/article/' . self::$doomedArticleId . '/comment/', ['content' => 'doomed comment']),
            'create the throwaway comment',
            [200]
        );
        self::$doomedCommentId = self::findInList(
            $owner->get('/article/' . self::$doomedArticleId . '/comment/'),
            'content',
            'doomed comment',
            'the throwaway comment'
        );

        $cid = self::$commentId;

        self::must(
            $owner->post('/article/' . $aid . '/comment/' . $cid . '/reply/0', ['content' => 'fixture reply']),
            'create the fixture reply',
            [200]
        );
        self::$replyId = self::findInList(
            $owner->get('/article/' . $aid . '/comment/' . $cid . '/reply/'),
            'content',
            'fixture reply',
            'the fixture reply'
        );

        self::must(
            $owner->post('/article/' . $aid . '/comment/' . $cid . '/reply/0', ['content' => 'doomed reply']),
            'create the throwaway reply',
            [200]
        );
        self::$doomedReplyId = self::findInList(
            $owner->get('/article/' . $aid . '/comment/' . $cid . '/reply/'),
            'content',
            'doomed reply',
            'the throwaway reply'
        );

        self::$tagId = self::findInList(
            $owner->get('/tag/search/smoke'),
            'name',
            'smoke',
            'the smoke tag'
        );

        // Two avatar uploads. The second becomes the current one, which leaves the
        // first non-current - and setAvatarByHistory refuses (with a 500, see the
        // report) to select an avatar that is already current, so {avatar} has to
        // point at the first.
        self::must(
            $owner->multipart('POST', '/user/avatar/upload', [], ['avatar' => self::$pngFile]),
            'upload the first avatar',
            [200]
        );
        self::must(
            $owner->multipart('POST', '/user/avatar/upload', [], ['avatar' => self::$pngFile]),
            'upload the second avatar',
            [200]
        );
        self::$avatarId = self::lowestId($owner->get('/user/avatar/history'), 'the avatar history');
    }

    /**
     * Give this run its own accounts and article titles.
     *
     * The suite cannot clean up after itself: users, articles, comments and
     * replies have no delete-all path, and DELETE /user/ is a soft delete that
     * leaves the email, mobile and nickname permanently taken anyway (the
     * uniqueness checks in App\Controller\User::register do not filter
     * soft-deleted rows). Fixed identities therefore only work against a database
     * that has just been created, and a second run gets "邮箱已被占用" during setup
     * rather than a test result.
     *
     * Deriving them from the run id makes the suite re-runnable against whatever is
     * already there, which matters far more locally than the tidiness of a fresh
     * database. CI still starts from an empty one.
     */
    private static function applyRunIdentities(): void
    {
        $digits = (string) preg_replace('/\D/', '', self::$runId);
        $tail   = substr(str_pad($digits, 8, '0'), -8);

        self::$ownerMobile = '138' . $tail;
        self::$ownerName   = 'smoke-owner' . substr($digits, -4);
        self::$ownerEmail  = 'smoke-owner-' . $digits . '@example.test';

        self::$otherMobile = '137' . $tail;
        self::$otherName   = 'smoke-other' . substr($digits, -4);
        self::$otherEmail  = 'smoke-other-' . $digits . '@example.test';

        self::$doomedMobile = '135' . $tail;
        self::$doomedName   = 'smoke-doomed' . substr($digits, -4);
        self::$doomedEmail  = 'smoke-doomed-' . $digits . '@example.test';

        self::$fixtureArticleTitle = 'fixture article ' . $digits;
        self::$doomedArticleTitle  = 'doomed article ' . $digits;
        self::$freeArticleTitle    = 'free article ' . $digits;
        self::$thrownArticleTitle  = 'thrown article ' . $digits;
    }

    /**
     * Log in, or register first when the account does not exist yet.
     *
     * @return array [id, token]
     */
    private static function createUser(string $label, string $mobile, string $password, string $name, string $email): array
    {
        $anon = self::anonymous();

        $login = $anon->post('/token/user', ['mobile' => $mobile, 'password' => $password]);
        if ($login->status() !== 200) {
            // 423 means a code is already live for this address - a previous run
            // left one behind because it failed between requesting the code and
            // registering. That code is still valid and is already in MailHog, so
            // it is read back and used rather than treated as an error. If it has
            // since expired the registration below fails with "请获取验证码", which
            // is the signal to clear the cache and start again.
            $since       = time();
            $codeRequest = $anon->get('/user/register/code', ['email' => $email]);
            self::must($codeRequest, 'request a registration code for ' . $label, [200, 423]);

            $code = MailCatcher::codeFor(
                self::$mailHogUrl,
                $email,
                $codeRequest->status() === 423 ? null : $since
            );

            self::must(
                $anon->post('/user/register', [
                    'mobile'   => $mobile,
                    'password' => $password,
                    'sex'      => 'MAN',
                    'name'     => $name,
                    'email'    => $email,
                    'code'     => $code,
                ]),
                'register ' . $label . ' (is the verification code mail reaching MailHog?)',
                [200]
            );

            $login = $anon->post('/token/user', ['mobile' => $mobile, 'password' => $password]);
        }

        self::must($login, 'log in as ' . $label, [200]);

        $token = $login->data()['token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new \RuntimeException('logging in as ' . $label . ' returned no token: ' . $login->describe());
        }

        $self = $anon->withToken($token)->get('/user/self');
        self::must($self, 'read back ' . $label . ' through GET /user/self', [200]);

        $id = $self->data()['id'] ?? null;
        if ($id === null) {
            throw new \RuntimeException('GET /user/self for ' . $label . ' returned no id: ' . $self->describe());
        }

        return [(int) $id, $token];
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @param array $expect acceptable statuses
     */
    public static function must(ApiResponse $response, string $what, array $expect): void
    {
        if ($response->transportError() !== null) {
            throw new \RuntimeException('could not ' . $what . ': ' . $response->describe());
        }

        if (!in_array($response->status(), $expect, true) || !$response->isEnvelope()) {
            throw new \RuntimeException(sprintf(
                'could not %s: expected %s, got %s',
                $what,
                implode('/', $expect),
                $response->describe()
            ));
        }
    }

    /**
     * The id of the list entry whose $field equals $value.
     */
    private static function findInList(ApiResponse $response, string $field, string $value, string $what): int
    {
        $list = self::listOf($response, $what);

        foreach ($list as $item) {
            if (isset($item[$field]) && (string) $item[$field] === $value) {
                if (!isset($item['id'])) {
                    throw new \RuntimeException('the entry for ' . $what . ' has no id: ' . json_encode($item));
                }

                return (int) $item['id'];
            }
        }

        throw new \RuntimeException(sprintf(
            'could not find %s (looking for %s=%s) in %s',
            $what,
            $field,
            $value,
            $response->describe()
        ));
    }

    /**
     * The lowest id in a plain list response, used for the avatar history, whose
     * entries have nothing but an id and a path to tell them apart.
     */
    private static function lowestId(ApiResponse $response, string $what): int
    {
        $list = self::listOf($response, $what);

        $ids = [];
        foreach ($list as $item) {
            if (isset($item['id'])) {
                $ids[] = (int) $item['id'];
            }
        }

        if (empty($ids)) {
            throw new \RuntimeException('no entries in ' . $what . ': ' . $response->describe());
        }

        return min($ids);
    }

    /**
     * @return array the `list` member of a paged response, or the data itself
     */
    private static function listOf(ApiResponse $response, string $what): array
    {
        if ($response->status() !== 200 || !$response->isEnvelope()) {
            throw new \RuntimeException('could not read ' . $what . ': ' . $response->describe());
        }

        $data = $response->data();
        $list = isset($data['list']) && is_array($data['list']) ? $data['list'] : $data;

        if (!is_array($list)) {
            throw new \RuntimeException('unexpected shape for ' . $what . ': ' . $response->describe());
        }

        return $list;
    }

    private static function writePng(): string
    {
        $path = sys_get_temp_dir() . '/smoke-avatar.png';
        file_put_contents($path, base64_decode(Routes::PNG_1X1_BASE64, true));

        return $path;
    }
}
