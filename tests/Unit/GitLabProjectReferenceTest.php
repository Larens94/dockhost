<?php

// GitLabProjectReferenceTest.php — Dokploy application payload → GitLab project path/ref.
//
// exports: GitLabProjectReferenceTest
// used_by: none
// rules:   none
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_git_toolkit_disc | gitlabRepository and customGitUrl cases.
// agent:   grok-4.7 | cursor | 2026-10-09 | s_github_source | GitHub owner/repository/branch.
// message:

namespace Tests\Unit;

use App\Services\GitLab\GitLabProjectReference;
use Tests\TestCase;

class GitLabProjectReferenceTest extends TestCase
{
    public function test_from_gitlab_repository_slug(): void
    {
        $reference = GitLabProjectReference::fromDokployApplication([
            'gitlabRepository' => 'silicore/vibesbridge',
            'gitlabBranch' => 'main',
        ]);

        $this->assertNotNull($reference);
        $this->assertSame('silicore/vibesbridge', $reference->projectPath);
        $this->assertSame('main', $reference->ref);
    }

    public function test_from_custom_git_url(): void
    {
        $reference = GitLabProjectReference::fromDokployApplication([
            'customGitUrl' => 'https://gitlab.example.com/acme/shop.git',
            'branch' => 'production',
        ]);

        $this->assertNotNull($reference);
        $this->assertSame('acme/shop', $reference->projectPath);
        $this->assertSame('production', $reference->ref);
    }

    public function test_from_github_owner_and_repository(): void
    {
        $reference = GitLabProjectReference::fromDokployApplication([
            'sourceType' => 'github',
            'owner' => 'Larens94',
            'repository' => 'vibebridge',
            'branch' => 'main',
            'githubId' => 'gh-1',
        ]);

        $this->assertNotNull($reference);
        $this->assertSame('Larens94/vibebridge', $reference->projectPath);
        $this->assertSame('main', $reference->ref);
    }
}
