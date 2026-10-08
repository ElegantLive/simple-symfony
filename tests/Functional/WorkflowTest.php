<?php

namespace App\Tests\Functional;

use App\Tests\Support\ApiResponse;
use App\Tests\Support\Context;
use App\Tests\Support\MailCatcher;
use PHPUnit\Framework\TestCase;

/**
 * Two complete journeys through the application, asserted on their results rather
 * than on "not a 500".
 *
 * The sweeps cannot check any of this. They create nothing they then rely on, and
 * they deliberately only look at the status, so a route that answers 200 with an
 * empty list looks the same as one that answers 200 with the article it was
 * supposed to store. Here:
 *
 *   1-2.  register with a code read out of MailHog, then log in
 *   3-5.  publish an article and find it in the self list, the public list and its
 *         own detail
 *   6-8.  comment on it, reply to that comment, and toggle likes
 *   9.    upload an avatar, read the history back, select a previous avatar
 *   10.   change the password and prove the old one stops working
 *   11.   delete the account and prove it can no longer log in
 *
 * The account is created fresh for each run with an id derived from the run id, so
 * rerunning does not collide with what the previous run left behind.
 *
 * These tests are chained with depends annotations on purpose: they describe one
 * journey, and when an early step breaks the later ones should be reported as
 * skipped rather than adding noise.
 *
 * (The word is spelled out rather than written as an annotation here: PHPUnit scans
 * the whole docblock, so the literal tag in a sentence is read as a real one and
 * every test then depends on a method named after the rest of the line.)
 */
final class WorkflowTest extends TestCase
{
    /** @var string */
    private static $mobile;

    /** @var string */
    private static $email;

    /** @var string */
    private static $name;

    /** @var string */
    private static $password = 'SmokeFlow1';

    /** @var string */
    private static $newPassword = 'SmokeFlow2';

    public static function setUpBeforeClass(): void
    {
        if (!Context::configured()) {
            return;
        }

        Context::bootstrap();

        $digits       = (string) preg_replace('/\D/', '', Context::runId());
        self::$mobile = '139' . substr(str_pad($digits, 8, '0'), -8);
        self::$email  = 'smoke-flow-' . $digits . '@example.test';
        self::$name   = 'smoke-flow' . substr($digits, -4);
    }

    private function skipUnlessConfigured(): void
    {
        if (!Context::configured()) {
            self::markTestSkipped(Context::notConfiguredReason());
        }
    }

    private function assertOk(ApiResponse $response, string $what): array
    {
        self::assertNull($response->transportError(), 'no usable response for ' . $what . ': ' . $response->describe());
        self::assertTrue($response->isEnvelope(), 'not the application envelope for ' . $what . ': ' . $response->describe());
        self::assertSame(200, $response->status(), 'failed to ' . $what . ': ' . $response->describe());

        return $response->data();
    }

    /**
     * Registration cannot be reached without a code that was mailed out, so the
     * mail is the fixture.
     */
    public function testRegistrationMailsACodeAndAcceptsIt(): string
    {
        $this->skipUnlessConfigured();

        $anonymous = Context::anonymous();

        $since = time();
        $this->assertOk(
            $anonymous->get('/user/register/code', ['email' => self::$email]),
            'request a registration code'
        );

        $code = MailCatcher::codeFor(Context::mailHogUrl(), self::$email, $since);

        self::assertMatchesRegularExpression('/^[0-9]{6}$/', $code, 'the mailed code should be six digits');

        $data = $this->assertOk(
            $anonymous->post('/user/register', [
                'mobile'   => self::$mobile,
                'password' => self::$password,
                'sex'      => 'MAN',
                'name'     => self::$name,
                'email'    => self::$email,
                'code'     => $code,
            ]),
            'register with the mailed code'
        );

        self::assertSame([], $data, 'registration returns no payload');

        $login = $this->assertOk(
            $anonymous->post('/token/user', ['mobile' => self::$mobile, 'password' => self::$password]),
            'log in with the new account'
        );

        self::assertArrayHasKey('token', $login);
        self::assertNotEmpty($login['token']);

        return (string) $login['token'];
    }

    /**
     * A code is only good for the address it was mailed to. The register endpoint
     * cannot be used to test single use as well: once an address has been
     * registered the uniqueness check answers 226 before the code is ever looked
     * at, so a second attempt with the same code and the same address never
     * reaches checkCode().
     *
     * @depends testRegistrationMailsACodeAndAcceptsIt
     */
    public function testACodeOnlyWorksForTheAddressItWasMailedTo(string $token): void
    {
        $this->skipUnlessConfigured();

        $anonymous = Context::anonymous();
        $address   = 'bound-' . self::$email;

        $since = time();
        $this->assertOk($anonymous->get('/user/register/code', ['email' => $address]), 'request a registration code');
        $code = MailCatcher::codeFor(Context::mailHogUrl(), $address, $since);

        // Its own mobile and nickname, derived from the run id. A fixed one is
        // already taken by whatever the previous run left behind - and the register
        // endpoint answers 226 for it before it ever looks at the code, so the
        // assertion below would report the wrong thing.
        $digits = (string) preg_replace('/\D/', '', Context::runId());
        $tail   = substr(str_pad($digits, 8, '0'), -8);

        $elsewhere = $anonymous->post('/user/register', [
            'mobile'   => '131' . $tail,
            'password' => self::$password,
            'sex'      => 'MAN',
            'name'     => 'smoke-elsewhere' . substr($digits, -3),
            'email'    => 'elsewhere-' . self::$email,
            'code'     => $code,
        ]);
        self::assertSame(
            404,
            $elsewhere->status(),
            'a code mailed to one address must not register another: ' . $elsewhere->describe()
        );

        $correct = $anonymous->post('/user/register', [
            'mobile'   => '134' . $tail,
            'password' => self::$password,
            'sex'      => 'MAN',
            'name'     => 'smoke-bound' . substr($digits, -3),
            'email'    => $address,
            'code'     => $code,
        ]);
        self::assertSame(
            200,
            $correct->status(),
            'and it must still work for the address it went to: ' . $correct->describe()
        );
    }

    /**
     * @depends testRegistrationMailsACodeAndAcceptsIt
     */
    public function testPublishingAnArticleAndFindingItEverywhere(string $token): int
    {
        $this->skipUnlessConfigured();

        $client = Context::anonymous()->withToken($token);

        $this->assertOk(
            $client->post('/article/', [
                'title'       => 'workflow article',
                'content'     => 'workflow article content',
                'description' => 'workflow article description',
                'tag'         => ['workflow'],
            ]),
            'publish an article'
        );

        $id = $this->findId($client->get('/article/list/self'), 'title', 'workflow article', 'the published article');

        $detail = $this->assertOk($client->get('/article/' . $id), 'read the article back');
        self::assertSame('workflow article', $detail['title'] ?? null);
        self::assertContains('workflow', array_column($detail['tag'] ?? [], 'name'), 'the tag should come back with the article');

        $public = $this->assertOk($client->get('/article/list'), 'list the public articles');
        $titles = array_column($public['list'] ?? [], 'title');
        self::assertContains('workflow article', $titles, 'the published article should be in the public list');

        return $id;
    }

    /**
     * @depends testPublishingAnArticleAndFindingItEverywhere
     */
    public function testUpdatingAnArticleChangesWhatComesBack(int $articleId): int
    {
        $this->skipUnlessConfigured();

        $token  = $this->flowToken();
        $client = Context::anonymous()->withToken($token);

        $this->assertOk(
            $client->put('/article/' . $articleId, [
                'title'       => 'workflow article',
                'content'     => 'workflow article content, revised',
                'description' => 'workflow article description',
                'tag'         => ['workflow'],
            ]),
            'update the article'
        );

        $detail = $this->assertOk($client->get('/article/' . $articleId), 'read the updated article');
        self::assertSame('workflow article content, revised', $detail['content'] ?? null);

        return $articleId;
    }

    /**
     * @depends testUpdatingAnArticleChangesWhatComesBack
     */
    public function testCommentingOnAnArticle(int $articleId): int
    {
        $this->skipUnlessConfigured();

        $client = Context::anonymous()->withToken($this->flowToken());

        $this->assertOk(
            $client->post('/article/' . $articleId . '/comment/', ['content' => 'workflow comment']),
            'comment on the article'
        );

        $commentId = $this->findId(
            $client->get('/article/' . $articleId . '/comment/'),
            'content',
            'workflow comment',
            'the comment'
        );

        $paged = $this->assertOk(
            $client->get('/article/' . $articleId . '/comment/', ['page' => 1, 'size' => 15]),
            'page the comments'
        );
        self::assertSame(1, $paged['total'] ?? null, 'the article should have exactly one comment');

        return $commentId;
    }

    /**
     * @depends testCommentingOnAnArticle
     */
    public function testReplyingToAComment(int $commentId): int
    {
        $this->skipUnlessConfigured();

        $articleId = (int) $this->findId(
            Context::anonymous()->withToken($this->flowToken())->get('/article/list/self'),
            'title',
            'workflow article',
            'the workflow article'
        );
        $client = Context::anonymous()->withToken($this->flowToken());

        $this->assertOk(
            $client->post('/article/' . $articleId . '/comment/' . $commentId . '/reply/0', ['content' => 'workflow reply']),
            'reply to the comment'
        );

        $list = $this->assertOk(
            $client->get('/article/' . $articleId . '/comment/' . $commentId . '/reply/'),
            'list the replies'
        );

        $replies = array_column($list['list'] ?? [], 'content');
        self::assertContains('workflow reply', $replies, 'the reply should be listed');

        $replyId = $this->findId(
            $client->get('/article/' . $articleId . '/comment/' . $commentId . '/reply/'),
            'content',
            'workflow reply',
            'the reply'
        );

        return $replyId;
    }

    /**
     * Like, dislike and the counters they move.
     *
     * @depends testReplyingToAComment
     */
    public function testTogglingLikes(int $replyId): void
    {
        $this->skipUnlessConfigured();

        $client    = Context::anonymous()->withToken($this->flowToken());
        $articleId = $this->findId(
            $client->get('/article/list/self'),
            'title',
            'workflow article',
            'the workflow article'
        );

        $before = $this->assertOk($client->get('/article/' . $articleId), 'read the article before liking');
        self::assertSame(0, (int) ($before['likeCount'] ?? -1), 'a fresh article starts with no likes');

        $this->assertOk($client->post('/article/' . $articleId . '/like'), 'like the article');

        $liked = $this->assertOk($client->get('/article/' . $articleId), 'read the article after liking');
        self::assertTrue($liked['isLike'] ?? null, 'isLike should be true for the liker');
        self::assertSame(1, (int) ($liked['likeCount'] ?? -1), 'the like should be counted');

        $this->assertOk($client->delete('/article/' . $articleId . '/like'), 'unlike the article');

        $unliked = $this->assertOk($client->get('/article/' . $articleId), 'read the article after unliking');
        self::assertFalse($unliked['isLike'] ?? null, 'isLike should be false again');
        self::assertSame(0, (int) ($unliked['likeCount'] ?? -1), 'the like should be taken back');

        $this->assertOk($client->post('/article/' . $articleId . '/dislike'), 'dislike the article');
        $disliked = $this->assertOk($client->get('/article/' . $articleId), 'read the article after disliking');
        self::assertTrue($disliked['isDisLike'] ?? null, 'isDisLike should be true');
        self::assertSame(1, (int) ($disliked['disLikeCount'] ?? -1), 'the dislike should be counted');
        $this->assertOk($client->delete('/article/' . $articleId . '/dislike'), 'undo the dislike');

        self::assertGreaterThan(0, $replyId, 'the reply id should have been threaded through');
    }

    /**
     * @depends testRegistrationMailsACodeAndAcceptsIt
     */
    public function testUploadingAndSelectingAnAvatar(string $token): void
    {
        $this->skipUnlessConfigured();

        $client = Context::anonymous()->withToken($token);

        $this->assertOk(
            $client->multipart('POST', '/user/avatar/upload', [], ['avatar' => Context::$pngFile]),
            'upload an avatar'
        );

        $history = $this->assertOk($client->get('/user/avatar/history'), 'read the avatar history');
        self::assertNotEmpty($history, 'the uploaded avatar should be in the history');

        $ids = array_column($history, 'id');
        self::assertNotEmpty($ids);

        // Selecting the avatar that was just uploaded is already current, and the
        // controller answers 201 "Already done" for that rather than 200.
        $selected = $client->put('/user/avatar/history', ['id' => min($ids)]);
        self::assertContains($selected->status(), [200, 201], 'selecting a previous avatar: ' . $selected->describe());

        $self = $this->assertOk($client->get('/user/self'), 'read the profile back');
        self::assertNotEmpty($self['avatar'] ?? null, 'the profile should carry the selected avatar');
    }

    /**
     * @depends testRegistrationMailsACodeAndAcceptsIt
     */
    public function testChangingThePasswordInvalidatesTheOldOne(string $token): string
    {
        $this->skipUnlessConfigured();

        $client = Context::anonymous()->withToken($token);

        $since = time();
        $this->assertOk($client->get('/user/password/code'), 'request a password change code');

        $code = MailCatcher::codeFor(Context::mailHogUrl(), self::$email, $since);

        $this->assertOk(
            $client->patch('/user/password', [
                'password'        => self::$newPassword,
                'confirmPassword' => self::$newPassword,
                'code'            => $code,
            ]),
            'change the password'
        );

        $newLogin = Context::anonymous()->post('/token/user', [
            'mobile'   => self::$mobile,
            'password' => self::$newPassword,
        ]);
        self::assertSame(200, $newLogin->status(), 'the new password should work: ' . $newLogin->describe());

        $oldLogin = Context::anonymous()->post('/token/user', [
            'mobile'   => self::$mobile,
            'password' => self::$password,
        ]);
        self::assertNotSame(200, $oldLogin->status(), 'the old password must stop working: ' . $oldLogin->describe());

        self::$password = self::$newPassword;

        // Handed on rather than re-derived: the token stays valid across a password
        // change (nothing revokes it), and the next test needs one.
        return $token;
    }

    /**
     * @depends testChangingThePasswordInvalidatesTheOldOne
     */
    public function testDeletingTheAccountStopsItLoggingIn(string $token): void
    {
        $this->skipUnlessConfigured();

        $client = Context::anonymous()->withToken($token);
        $this->assertOk($client->delete('/user/'), 'delete the account');

        $login = Context::anonymous()->post('/token/user', [
            'mobile'   => self::$mobile,
            'password' => self::$password,
        ]);
        self::assertNotSame(200, $login->status(), 'a deleted account must not log in: ' . $login->describe());
    }

    private function flowToken(): string
    {
        $login = Context::anonymous()->post('/token/user', [
            'mobile'   => self::$mobile,
            'password' => self::$password,
        ]);

        if ($login->status() !== 200) {
            self::fail('could not log in as the workflow account: ' . $login->describe());
        }

        return (string) ($login->data()['token'] ?? '');
    }

    /**
     * @param mixed $value
     */
    private function findId(ApiResponse $response, string $field, $value, string $what): int
    {
        self::assertTrue($response->isEnvelope(), 'not the application envelope: ' . $response->describe());

        $data = $response->data();
        $list = isset($data['list']) && is_array($data['list']) ? $data['list'] : $data;

        foreach ($list as $item) {
            if (isset($item[$field]) && $item[$field] === $value) {
                return (int) $item['id'];
            }
        }

        self::fail(sprintf('could not find %s in %s', $what, $response->describe()));

        return 0;
    }
}
