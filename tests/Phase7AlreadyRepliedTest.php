<?php

/**
 * Phase 7: Already-Replied Edge Cases
 * 
 * Tests scenarios 7.1–7.4 from the maze scenario matrix.
 * Once a challenge is marked 'replied', the user cannot re-trigger the maze.
 */
class Phase7AlreadyRepliedTest extends MazeTestCase
{
    /**
     * Helper: seed a 'replied' challenge state.
     */
    private function seedRepliedState(): void
    {
        $ctMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $pkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('replied', $this->pkGood(), $pkMsgId, $ctMsgId);
        // Mark these messages as already processed
        $this->mazeProgressRepo->setLastProcessedId($this->testUserId, $pkMsgId);
    }

    /** @test 7.1 — Status 'replied', send new CT → skipped */
    public function testRepliedSendCTSkipped(): void
    {
        $this->seedRepliedState();

        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('already_replied', $result);
        // No new admin reply should be generated (only the old ones from seeding)
        $this->assertSame(0, $this->countAdminReplies($this->testUserId));
    }

    /** @test 7.2 — Status 'replied', send new PK → skipped */
    public function testRepliedSendPKSkipped(): void
    {
        $this->seedRepliedState();

        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('already_replied', $result);
        $this->assertSame(0, $this->countAdminReplies($this->testUserId));
    }

    /** @test 7.3 — Status 'replied', send both → skipped */
    public function testRepliedSendBothSkipped(): void
    {
        $this->seedRepliedState();

        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('already_replied', $result);
        $this->assertSame(0, $this->countAdminReplies($this->testUserId));
    }

    /** @test 7.4 — Status 'replied', delete old + send new → still skipped */
    public function testRepliedDeleteOldSendNewStillSkipped(): void
    {
        $this->seedRepliedState();

        // Get the old message IDs from the challenge
        $challenge = $this->getLatestChallenge($this->testUserId);

        // Delete old messages (FK cascades to NULL)
        if ($challenge['encrypted_username_msg_id']) {
            $this->deleteMessage($challenge['encrypted_username_msg_id']);
        }
        if ($challenge['public_key_msg_id']) {
            $this->deleteMessage($challenge['public_key_msg_id']);
        }

        // Send fresh content
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        // Status is still 'replied' — user cannot re-trigger
        $this->assertSame('already_replied', $result);
        $this->assertSame(0, $this->countAdminReplies($this->testUserId));
    }
}
