<?php

namespace App\Tests\Support;

/**
 * Every route the application exposes, plus the probes the smoke tests send at
 * them.
 *
 * ROUTES is the inventory. It is compared against `bin/console debug:router`
 * by the CI step "Route registry matches the router" (see
 * .github/workflows/ci.yml), so a route that is added or removed without being
 * registered here fails the build instead of quietly going untested.
 *
 * A probe is one concrete request. Field meanings:
 *
 *   id      unique dataset name, so PHPUnit reports it verbatim
 *   route   the router's route name, which is what makes coverage measurable
 *   method  HTTP method
 *   path    concrete path; {placeholders} are filled in from Context
 *   as      whose token to send: null, 'owner', 'other' or 'doomed'
 *   query   query string (NOT part of the signature or the parameter MAC - see
 *           ApiClient for why)
 *   body    JSON body
 *   raw     body sent verbatim, for probing what an undecodable body does
 *   fields  multipart form fields
 *   files   multipart files, name => path
 *   expect  acceptable statuses. When absent the probe only has to answer with
 *           the application's JSON envelope and a status below 500, which is the
 *           bar the suite is built on.
 *   note    why the probe exists
 */
final class Routes
{
    /**
     * name => [method, path] for every route in the router.
     */
    const ROUTES = [
        // Article
        'getSelfArticleList'   => ['GET', '/article/list/self'],
        'getArticleList'       => ['GET', '/article/list'],
        'articleDetail'        => ['GET', '/article/{id}'],
        'createArticle'        => ['POST', '/article/'],
        'updateArticle'        => ['PUT', '/article/{id}'],
        'deleteArticle'        => ['DELETE', '/article/{id}'],
        // Comment
        'getCommentsByArticle' => ['GET', '/article/{id}/comment/'],
        'newCommentForArticle' => ['POST', '/article/{id}/comment/'],
        'deleteArticleComment' => ['DELETE', '/article/{id}/comment/{commentId}'],
        // The demo controller, still routed.
        'app_index_index'      => ['PUT', '/index/index'],
        'app_index_second'     => ['DELETE', '/index/second'],
        'app_index_three'      => ['PATCH', '/index/three'],
        // Reply
        'getCommentReplies'    => ['GET', '/article/{articleId}/comment/{commentId}/reply/'],
        'replyArticleComment'  => ['POST', '/article/{articleId}/comment/{commentId}/reply/{replyId}'],
        'deleteCommentReply'   => ['DELETE', '/article/{articleId}/comment/{commentId}/reply/{replyId}'],
        // Tag
        'getHotTags'           => ['GET', '/tag/hot'],
        'searchTagByKey'       => ['GET', '/tag/search/{key}'],
        // ThirdRelation
        'addLikeForArticle'    => ['POST', '/article/{id}/{type}'],
        'cancelLikeForArticle' => ['DELETE', '/article/{id}/{type}'],
        'addLikeForComment'    => ['POST', '/comment/{id}/{type}'],
        'cancelLikeForComment' => ['DELETE', '/comment/{id}/{type}'],
        'addLikeForReply'      => ['POST', '/reply/{id}/{type}'],
        'cancelLikeForReply'   => ['DELETE', '/reply/{id}/{type}'],
        // Token
        'app_token_user'       => ['POST', '/token/user'],
        // User
        'userRegister'         => ['POST', '/user/register'],
        'randInfo'             => ['GET', '/user/info/rand'],
        'userInfo'             => ['GET', '/user/info/{id}'],
        'getSelfInfo'          => ['GET', '/user/self'],
        'updateUser'           => ['PATCH', '/user/'],
        'registerCode'         => ['GET', '/user/register/code'],
        'changePasswordCode'   => ['GET', '/user/password/code'],
        'changePassword'       => ['PATCH', '/user/password'],
        'deleteUser'           => ['DELETE', '/user/'],
        'setAvatarByUpload'    => ['POST', '/user/avatar/upload'],
        'setAvatarByHistory'   => ['PUT', '/user/avatar/history'],
        'getAvatarHistory'     => ['GET', '/user/avatar/history'],
    ];

    /**
     * A tiny valid PNG, 1x1, as a base64 string. Used for the avatar upload so
     * the suite does not depend on the gd extension, which CI does not install.
     */
    const PNG_1X1_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    /**
     * @return array
     */
    public static function probes(): array
    {
        $p       = [];
        $article = '{article}';
        $comment = '{comment}';
        $reply   = '{reply}';

        // ---------------------------------------------------------------- public

        $p[] = [
            'id'     => 'POST /token/user (login)',
            'route'  => 'app_token_user',
            'method' => 'POST',
            'path'   => '/token/user',
            'body'   => ['mobile' => '{mobile}', 'password' => '{password}'],
            'expect' => [200],
            'note'   => 'the only way to get a token; every other authenticated probe depends on it',
        ];

        $p[] = [
            'id'     => 'POST /user/register (code never issued)',
            'route'  => 'userRegister',
            'method' => 'POST',
            'path'   => '/user/register',
            'body'   => [
                'mobile'   => '13800009001',
                'password' => 'SmokePass1',
                'sex'      => 'MAN',
                'name'     => 'smoke-register',
                'email'    => 'smoke-register@example.test',
                'code'     => '123456',
            ],
            'note'   => 'valid body, but the code was never issued -> 404; the full success path is WorkflowTest',
        ];

        $p[] = [
            'id'     => 'POST /user/register (empty body)',
            'route'  => 'userRegister',
            'method' => 'POST',
            'path'   => '/user/register',
            'raw'    => '',
            'note'   => 'App\Service\Request::getData() returns null for this, not []',
        ];

        $p[] = [
            'id'     => 'GET /user/register/code',
            'route'  => 'registerCode',
            'method' => 'GET',
            'path'   => '/user/register/code',
            'query'  => ['email' => '{codeEmail}'],
            'note'   => 'mails a code through MailHog; a repeat inside 720s answers 423, still not a 500',
        ];

        $p[] = [
            'id'     => 'GET /user/register/code (no email)',
            'route'  => 'registerCode',
            'method' => 'GET',
            'path'   => '/user/register/code',
            'expect' => [400],
            'note'   => 'missing query parameter must be a validation error',
        ];

        $p[] = [
            'id'     => 'GET /user/info/rand',
            'route'  => 'randInfo',
            'method' => 'GET',
            'path'   => '/user/info/rand',
            'expect' => [200],
            'note'   => 'needs at least three users and no gaps in the id range - see the report',
        ];

        $p[] = [
            'id'     => 'GET /user/info/{id}',
            'route'  => 'userInfo',
            'method' => 'GET',
            'path'   => '/user/info/{owner}',
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'GET /user/info/999999',
            'route'  => 'userInfo',
            'method' => 'GET',
            'path'   => '/user/info/999999',
            'expect' => [404],
        ];

        $p[] = [
            'id'     => 'PUT /index/index',
            'route'  => 'app_index_index',
            'method' => 'PUT',
            'path'   => '/index/index',
            'body'   => ['mobile' => '13800009002'],
            'expect' => [200],
            'note'   => 'leftover demo controller, still routed and reachable',
        ];

        $p[] = [
            'id'     => 'DELETE /index/second',
            'route'  => 'app_index_second',
            'method' => 'DELETE',
            'path'   => '/index/second',
            'query'  => ['mobile' => '13800009002'],
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'PATCH /index/three',
            'route'  => 'app_index_three',
            'method' => 'PATCH',
            'path'   => '/index/three',
            'body'   => ['mobile' => '13800009002'],
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'GET /tag/hot',
            'route'  => 'getHotTags',
            'method' => 'GET',
            'path'   => '/tag/hot',
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'GET /tag/search/{key}',
            'route'  => 'searchTagByKey',
            'method' => 'GET',
            'path'   => '/tag/search/smoke',
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'GET /tag/search/{key}?page=2&size=5',
            'route'  => 'searchTagByKey',
            'method' => 'GET',
            'path'   => '/tag/search/smoke',
            'query'  => ['page' => 2, 'size' => 5],
            'expect' => [200],
            'note'   => 'Tag reads page/size by hand rather than through a validator',
        ];

        $p[] = [
            'id'     => 'GET /article/list (no token)',
            'route'  => 'getArticleList',
            'method' => 'GET',
            'path'   => '/article/list',
            'note'   => 'optional auth: the action itself must survive a missing token',
        ];

        $p[] = [
            'id'     => 'GET /article/{id} (no token)',
            'route'  => 'articleDetail',
            'method' => 'GET',
            'path'   => '/article/' . $article,
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'GET /article/999999',
            'route'  => 'articleDetail',
            'method' => 'GET',
            'path'   => '/article/999999',
            'expect' => [404],
        ];

        $p[] = [
            'id'     => 'GET /article/{id}/comment/',
            'route'  => 'getCommentsByArticle',
            'method' => 'GET',
            'path'   => '/article/' . $article . '/comment/',
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'GET /article/{id}/comment/{commentId}/reply/',
            'route'  => 'getCommentReplies',
            'method' => 'GET',
            'path'   => '/article/' . $article . '/comment/' . $comment . '/reply/',
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'GET reply list with ?page=1&size=15',
            'route'  => 'getCommentReplies',
            'method' => 'GET',
            'path'   => '/article/' . $article . '/comment/' . $comment . '/reply/',
            'query'  => ['page' => 1, 'size' => 15],
            'expect' => [200],
            'note'   => 'Reply::getPager assigns to $array_key instead of $$array_key, unlike the other three pagers',
        ];

        // --------------------------------------------------------- authenticated

        $p[] = [
            'id'     => 'GET /user/self',
            'route'  => 'getSelfInfo',
            'method' => 'GET',
            'path'   => '/user/self',
            'as'     => 'owner',
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'PATCH /user/',
            'route'  => 'updateUser',
            'method' => 'PATCH',
            'path'   => '/user/',
            'as'     => 'owner',
            'body'   => ['sex' => 'MAN'],
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'GET /user/password/code',
            'route'  => 'changePasswordCode',
            'method' => 'GET',
            'path'   => '/user/password/code',
            'as'     => 'other',
            'note'   => 'sent as the second user so the owner keeps a clean code slot',
        ];

        $p[] = [
            'id'     => 'PATCH /user/password (wrong code)',
            'route'  => 'changePassword',
            'method' => 'PATCH',
            'path'   => '/user/password',
            'as'     => 'other',
            'body'   => ['password' => 'SmokePass9', 'confirmPassword' => 'SmokePass9', 'code' => '000000'],
        ];

        $p[] = [
            'id'     => 'DELETE /user/ (own account)',
            'route'  => 'deleteUser',
            'method' => 'DELETE',
            'path'   => '/user/',
            'as'     => 'doomed',
            'expect' => [200],
            'note'   => 'soft-deletes the throwaway account, never the fixtures the other probes need',
        ];

        $p[] = [
            'id'     => 'POST /user/avatar/upload',
            'route'  => 'setAvatarByUpload',
            'method' => 'POST',
            'path'   => '/user/avatar/upload',
            'as'     => 'owner',
            'files'  => ['avatar' => '{pngFile}'],
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'GET /user/avatar/history',
            'route'  => 'getAvatarHistory',
            'method' => 'GET',
            'path'   => '/user/avatar/history',
            'as'     => 'owner',
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'PUT /user/avatar/history',
            'route'  => 'setAvatarByHistory',
            'method' => 'PUT',
            'path'   => '/user/avatar/history',
            'as'     => 'owner',
            'body'   => ['id' => '{avatar}'],
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'GET /article/list/self',
            'route'  => 'getSelfArticleList',
            'method' => 'GET',
            'path'   => '/article/list/self',
            'as'     => 'owner',
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'GET /article/list (with token)',
            'route'  => 'getArticleList',
            'method' => 'GET',
            'path'   => '/article/list',
            'as'     => 'owner',
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'GET /article/list?by=viewCount',
            'route'  => 'getArticleList',
            'method' => 'GET',
            'path'   => '/article/list',
            'as'     => 'owner',
            'query'  => ['page' => 1, 'size' => 5, 'order' => 'asc', 'by' => 'viewCount'],
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'POST /article/',
            'route'  => 'createArticle',
            'method' => 'POST',
            'path'   => '/article/',
            'as'     => 'owner',
            'body'   => [
                'title'       => 'smoke article',
                'content'     => 'smoke content for the sweep',
                'description' => 'smoke description',
                'tag'         => ['smoke'],
            ],
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'PUT /article/{id}',
            'route'  => 'updateArticle',
            'method' => 'PUT',
            'path'   => '/article/' . $article,
            'as'     => 'owner',
            'body'   => [
                'title'       => 'smoke article, revised',
                'content'     => 'smoke content, revised',
                'description' => 'smoke description, revised',
                'tag'         => ['smoke'],
            ],
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'DELETE /article/{id}',
            'route'  => 'deleteArticle',
            'method' => 'DELETE',
            'path'   => '/article/{thrownArticle}',
            'as'     => 'owner',
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'POST /article/{id}/comment/',
            'route'  => 'newCommentForArticle',
            'method' => 'POST',
            'path'   => '/article/{freeArticle}/comment/',
            'as'     => 'owner',
            'body'   => ['content' => 'smoke comment'],
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'DELETE /article/{id}/comment/{commentId}',
            'route'  => 'deleteArticleComment',
            'method' => 'DELETE',
            'path'   => '/article/{doomedArticle}/comment/{doomedComment}',
            'as'     => 'owner',
            'expect' => [200],
            'note'   => 'the controller requires the caller to be both the commenter and the article author',
        ];

        $p[] = [
            'id'     => 'POST reply (top level)',
            'route'  => 'replyArticleComment',
            'method' => 'POST',
            'path'   => '/article/' . $article . '/comment/' . $comment . '/reply/0',
            'as'     => 'owner',
            'body'   => ['content' => 'smoke reply'],
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'POST reply (nested)',
            'route'  => 'replyArticleComment',
            'method' => 'POST',
            'path'   => '/article/' . $article . '/comment/' . $comment . '/reply/' . $reply,
            'as'     => 'owner',
            'body'   => ['content' => 'smoke nested reply'],
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'DELETE /reply/{replyId}',
            'route'  => 'deleteCommentReply',
            'method' => 'DELETE',
            'path'   => '/article/' . $article . '/comment/' . $comment . '/reply/{doomedReply}',
            'as'     => 'owner',
            'expect' => [200],
        ];

        foreach (['article' => $article, 'comment' => $comment, 'reply' => $reply] as $kind => $id) {
            $p[] = [
                'id'     => 'POST /' . $kind . '/{id}/like',
                'route'  => 'addLikeFor' . ucfirst($kind),
                'method' => 'POST',
                'path'   => '/' . $kind . '/' . $id . '/like',
                'as'     => 'other',
                'expect' => [200],
            ];

            $p[] = [
                'id'     => 'DELETE /' . $kind . '/{id}/like',
                'route'  => 'cancelLikeFor' . ucfirst($kind),
                'method' => 'DELETE',
                'path'   => '/' . $kind . '/' . $id . '/like',
                'as'     => 'other',
                'expect' => [200],
            ];
        }

        $p[] = [
            'id'     => 'POST /article/{id}/dislike',
            'route'  => 'addLikeForArticle',
            'method' => 'POST',
            'path'   => '/article/' . $article . '/dislike',
            'as'     => 'other',
            'expect' => [200],
            'note'   => 'the other half of the like/dislike branch',
        ];

        $p[] = [
            'id'     => 'DELETE /article/{id}/dislike',
            'route'  => 'cancelLikeForArticle',
            'method' => 'DELETE',
            'path'   => '/article/' . $article . '/dislike',
            'as'     => 'other',
            'expect' => [200],
        ];

        $p[] = [
            'id'     => 'POST /article/{id}/bogus',
            'route'  => 'addLikeForArticle',
            'method' => 'POST',
            'path'   => '/article/' . $article . '/bogus',
            'as'     => 'other',
            'expect' => [400],
            'note'   => 'only like and dislike are accepted',
        ];

        return $p;
    }
}
