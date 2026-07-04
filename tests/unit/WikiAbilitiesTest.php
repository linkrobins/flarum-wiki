<?php

/*
 * This file is part of linkrobins/wiki.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace LinkRobins\Wiki\Tests\unit;

use Flarum\User\User;
use LinkRobins\Wiki\Access\WikiAbilities;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\Test;

class WikiAbilitiesTest extends MockeryTestCase
{
    /**
     * Build a User test double whose isGuest/isAdmin/hasPermission answers are
     * controlled per-case. We mock only those three interactions -- the whole
     * contract of WikiAbilities -- rather than booting an app.
     *
     * @param  list<string>  $permissions
     */
    private function user(bool $guest, bool $admin = false, array $permissions = []): User
    {
        $user = m::mock(User::class);
        $user->shouldReceive('isGuest')->andReturn($guest);
        $user->shouldReceive('isAdmin')->andReturn($admin);
        $user->shouldReceive('hasPermission')->andReturnUsing(
            fn (string $permission) => in_array($permission, $permissions, true)
        );

        return $user;
    }

    #[Test]
    public function guests_are_never_editors(): void
    {
        $this->assertFalse(WikiAbilities::isEditor($this->user(guest: true)));
    }

    #[Test]
    public function admins_are_editors(): void
    {
        $this->assertTrue(WikiAbilities::isEditor($this->user(guest: false, admin: true)));
    }

    #[Test]
    public function users_holding_edit_articles_are_editors(): void
    {
        $user = $this->user(guest: false, permissions: [WikiAbilities::EDIT_ARTICLES]);

        $this->assertTrue(WikiAbilities::isEditor($user));
    }

    #[Test]
    public function plain_users_are_not_editors(): void
    {
        $this->assertFalse(WikiAbilities::isEditor($this->user(guest: false)));
    }

    #[Test]
    public function creating_needs_the_create_article_permission(): void
    {
        $this->assertTrue(WikiAbilities::canCreate($this->user(guest: false, admin: true)));
        $this->assertTrue(WikiAbilities::canCreate(
            $this->user(guest: false, permissions: [WikiAbilities::CREATE_ARTICLE])
        ));

        // editArticles alone does NOT grant creation.
        $this->assertFalse(WikiAbilities::canCreate(
            $this->user(guest: false, permissions: [WikiAbilities::EDIT_ARTICLES])
        ));
        $this->assertFalse(WikiAbilities::canCreate($this->user(guest: true)));
    }

    #[Test]
    public function commenting_needs_the_comment_permission(): void
    {
        $this->assertTrue(WikiAbilities::canComment($this->user(guest: false, admin: true)));
        $this->assertTrue(WikiAbilities::canComment(
            $this->user(guest: false, permissions: [WikiAbilities::COMMENT])
        ));

        $this->assertFalse(WikiAbilities::canComment($this->user(guest: false)));
        $this->assertFalse(WikiAbilities::canComment($this->user(guest: true)));
    }
}
