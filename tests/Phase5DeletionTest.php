<?php

/**
 * Phase 5: Deletion Scenarios
 * 
 * Tests scenarios 5.1.1–5.5.3 from the maze scenario matrix.
 * Tests ON DELETE SET NULL cascade behavior and its effect on persistent memory.
 */
class Phase5DeletionTest extends MazeTestCase
{
    // =================================================================
    // 5.1 — User Deletes Before Processor Runs (Same Batch)
    // =================================================================

    /** @test 5.1.1 — Sends CT, deletes it, sends nothing else → no maze content */
    public function testDeleteCTBeforeProcessorNoContent(): void
    {
        $ctMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->deleteMessage($ctMsgId);

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('no_messages', $result);
        $this->assertNull($this->getAdminReplyTo($this->testUserId));
    }

    /** @test 5.1.2 — Sends CT1, deletes CT1, sends CT2 → CT2 treated as fresh */
    public function testDeleteCT1SendCT2TreatedAsFresh(): void
    {
        $ct1Id = $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());
        $this->deleteMessage($ct1Id);
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I decrypted your secret (and your identity checked out!), but I don't have your Public Key yet! How am I supposed to reply securely?",
            $reply
        );
    }

    /** @test 5.1.3 — Sends PK, deletes it → no maze content */
    public function testDeletePKBeforeProcessorNoContent(): void
    {
        $pkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->deleteMessage($pkMsgId);

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('no_messages', $result);
        $this->assertNull($this->getAdminReplyTo($this->testUserId));
    }

    /** @test 5.1.4 — Sends CT + PK, deletes PK → CT only → sendMissingKeyHint */
    public function testDeletePKBeforeProcessorCTOnly(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $pkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->deleteMessage($pkMsgId);

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I decrypted your secret (and your identity checked out!), but I don't have your Public Key yet! How am I supposed to reply securely?",
            $reply
        );
    }

    /** @test 5.1.5 — Sends CT + PK, deletes CT → PK only → sendMissingMessageHint */
    public function testDeleteCTBeforeProcessorPKOnly(): void
    {
        $ctMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->deleteMessage($ctMsgId);

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingMessageHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Nice key! Now, are you going to send me that encrypted username or what?",
            $reply
        );
    }

    /** @test 5.1.6 — Sends CT + PK, deletes both → no maze content */
    public function testDeleteBothBeforeProcessorNoContent(): void
    {
        $ctMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $pkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->deleteMessage($ctMsgId);
        $this->deleteMessage($pkMsgId);

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('no_messages', $result);
        $this->assertNull($this->getAdminReplyTo($this->testUserId));
    }

    // =================================================================
    // 5.2 — User Deletes Old CT Message, Then Sends New Content
    // =================================================================

    /** @test 5.2.1 — DB had CT✓, delete CT msg, send PK✓ → PK only (CT gone) */
    public function testDeleteOldCTSendPKGoodMissingMessage(): void
    {
        // Setup: previous CT was stored
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->seedChallengeState('pending_key', null, null, $oldCtMsgId);
        // Simulate deletion → ON DELETE SET NULL cascades
        $this->deleteMessage($oldCtMsgId);

        // New batch: user sends PK only
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingMessageHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Nice key! Now, are you going to send me that encrypted username or what?",
            $reply
        );
    }

    /** @test 5.2.2 — DB had CT✓, delete CT msg, send PK✗ → PK only (CT gone) */
    public function testDeleteOldCTSendPKBadMissingMessage(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->seedChallengeState('pending_key', null, null, $oldCtMsgId);
        $this->deleteMessage($oldCtMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingMessageHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "That Public Key doesn't look right—generate a new one or make sure you copied it correctly. And I'm still waiting on that encrypted message!",
            $reply
        );
    }

    /** @test 5.2.3 — DB had CT✓, delete CT msg, send CT✓ → CT only (fresh, not override) */
    public function testDeleteOldCTSendNewCTFresh(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->seedChallengeState('pending_key', null, null, $oldCtMsgId);
        $this->deleteMessage($oldCtMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        // Fresh treatment — no override prefix
        $this->assertSame(
            "I decrypted your secret (and your identity checked out!), but I don't have your Public Key yet! How am I supposed to reply securely?",
            $reply
        );
    }

    /** @test 5.2.4 — DB had CT✓+PK✓, delete CT msg, send PK✓ → PK override, CT gone */
    public function testDeleteOldCTFromFullStateSendPKOverride(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('invalid', $this->pkGood(), $oldPkMsgId, $oldCtMsgId);
        $this->deleteMessage($oldCtMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingMessageHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        // Key override since old PK msg still exists
        $this->assertSame(
            "New key, who dis? Just kidding—I've swapped your old key for this one. Still waiting on that encrypted message, though!",
            $reply
        );
    }

    /** @test 5.2.5 — DB had CT✓+PK✓, delete CT msg, send CT✓ → processChallenge SUCCESS */
    public function testDeleteOldCTFromFullStateSendNewCTSuccess(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('invalid', $this->pkGood(), $oldPkMsgId, $oldCtMsgId);
        $this->deleteMessage($oldCtMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $decryptedMessage = $this->decryptAdminReply($reply);
        $this->assertSame(
            MAZE_BONUS_MESSAGE,
            $decryptedMessage,
            'The decrypted admin reply did not match the expected bonus message.'
        );
        $challenge = $this->getLatestChallenge($this->testUserId);
        $this->assertSame('replied', $challenge['status']);
    }

    // =================================================================
    // 5.3 — User Deletes Old PK Message, Then Sends New Content
    // =================================================================

    /** @test 5.3.1 — DB had PK✓, delete PK msg, send CT✓ → CT only (PK gone) */
    public function testDeleteOldPKSendCTGoodMissingKey(): void
    {
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('pending_message', $this->pkGood(), $oldPkMsgId, null);
        $this->deleteMessage($oldPkMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I decrypted your secret (and your identity checked out!), but I don't have your Public Key yet! How am I supposed to reply securely?",
            $reply
        );
    }

    /** @test 5.3.2 — DB had PK✓, delete PK msg, send CT✗ → CT only (PK gone) */
    public function testDeleteOldPKSendCTBadMissingKey(): void
    {
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('pending_message', $this->pkGood(), $oldPkMsgId, null);
        $this->deleteMessage($oldPkMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I tried to decrypt your message but it's unreadable. Also, I don't have your Public Key yet!",
            $reply
        );
    }

    /** @test 5.3.3 — DB had PK✓, delete PK msg, send PK✓ → PK only (fresh, not override) */
    public function testDeleteOldPKSendNewPKFresh(): void
    {
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('pending_message', $this->pkGood(), $oldPkMsgId, null);
        $this->deleteMessage($oldPkMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingMessageHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        // Fresh treatment — no override prefix
        $this->assertSame(
            "Nice key! Now, are you going to send me that encrypted username or what?",
            $reply
        );
    }

    /** @test 5.3.4 — DB had CT✓+PK✓, delete PK msg, send CT✓ → msg override, PK gone */
    public function testDeleteOldPKFromFullStateSendCTOverride(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('invalid', $this->pkGood(), $oldPkMsgId, $oldCtMsgId);
        $this->deleteMessage($oldPkMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        // Msg override since old CT msg still exists
        $this->assertSame(
            "I've updated your secret message (it decrypted successfully and your identity checked out!), but I don't have your Public Key yet! How am I supposed to reply securely?",
            $reply
        );
    }

    /** @test 5.3.5 — DB had CT✓+PK✓, delete PK msg, send PK✓ → processChallenge SUCCESS */
    public function testDeleteOldPKFromFullStateSendNewPKSuccess(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('invalid', $this->pkGood(), $oldPkMsgId, $oldCtMsgId);
        $this->deleteMessage($oldPkMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $decryptedMessage = $this->decryptAdminReply($reply);
        $this->assertSame(
            MAZE_BONUS_MESSAGE,
            $decryptedMessage,
            'The decrypted admin reply did not match the expected bonus message.'
        );
        $challenge = $this->getLatestChallenge($this->testUserId);
        $this->assertSame('replied', $challenge['status']);
    }

    // =================================================================
    // 5.4 — User Deletes Both Old Messages, Then Sends New Content
    // =================================================================

    /** @test 5.4.1 — Delete both, send CT✓ → CT only (all fresh) */
    public function testDeleteBothSendCTFresh(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('invalid', $this->pkGood(), $oldPkMsgId, $oldCtMsgId);
        $this->deleteMessage($oldCtMsgId);
        $this->deleteMessage($oldPkMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I decrypted your secret (and your identity checked out!), but I don't have your Public Key yet! How am I supposed to reply securely?",
            $reply
        );
    }

    /** @test 5.4.2 — Delete both, send PK✓ → PK only (all fresh) */
    public function testDeleteBothSendPKFresh(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('invalid', $this->pkGood(), $oldPkMsgId, $oldCtMsgId);
        $this->deleteMessage($oldCtMsgId);
        $this->deleteMessage($oldPkMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingMessageHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Nice key! Now, are you going to send me that encrypted username or what?",
            $reply
        );
    }

    /** @test 5.4.3 — Delete both, send CT✓+PK✓ → both fresh → SUCCESS */
    public function testDeleteBothSendBothFreshSuccess(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('invalid', $this->pkGood(), $oldPkMsgId, $oldCtMsgId);
        $this->deleteMessage($oldCtMsgId);
        $this->deleteMessage($oldPkMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $decryptedMessage = $this->decryptAdminReply($reply);
        $this->assertSame(
            MAZE_BONUS_MESSAGE,
            $decryptedMessage,
            'The decrypted admin reply did not match the expected bonus message.'
        );
        $challenge = $this->getLatestChallenge($this->testUserId);
        $this->assertSame('replied', $challenge['status']);
    }

    /** @test 5.4.4 — Delete both, send nothing → no new maze content */
    public function testDeleteBothSendNothingNoContent(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('invalid', $this->pkGood(), $oldPkMsgId, $oldCtMsgId);
        $this->deleteMessage($oldCtMsgId);
        $this->deleteMessage($oldPkMsgId);

        // No new messages — processor shouldn't wake up
        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('no_messages', $result);
        $this->assertNull($this->getAdminReplyTo($this->testUserId));
    }

    // =================================================================
    // 5.5 — User Deletes Old Content, Sends Nothing New
    // (Processor doesn't wake up — no new maze content)
    // =================================================================

    /** @test 5.5.1 — DB had CT only, delete CT msg → processor doesn't wake up */
    public function testDeleteCTOnlySendNothing(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->seedChallengeState('pending_key', null, null, $oldCtMsgId);
        // Simulate: processor already ran and processed up to this message
        $this->mazeProgressRepo->setLastProcessedId($this->testUserId, $oldCtMsgId);
        $this->deleteMessage($oldCtMsgId);

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('no_messages', $result);
        $this->assertNull($this->getAdminReplyTo($this->testUserId));
    }

    /** @test 5.5.2 — DB had PK only, delete PK msg → processor doesn't wake up */
    public function testDeletePKOnlySendNothing(): void
    {
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('pending_message', $this->pkGood(), $oldPkMsgId, null);
        $this->mazeProgressRepo->setLastProcessedId($this->testUserId, $oldPkMsgId);
        $this->deleteMessage($oldPkMsgId);

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('no_messages', $result);
        $this->assertNull($this->getAdminReplyTo($this->testUserId));
    }

    /** @test 5.5.3 — DB had both, delete one → processor doesn't wake up */
    public function testDeleteFromBothSendNothing(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('invalid', $this->pkGood(), $oldPkMsgId, $oldCtMsgId);
        // Simulate: processor already ran and processed up to the PK message
        $this->mazeProgressRepo->setLastProcessedId($this->testUserId, $oldPkMsgId);
        $this->deleteMessage($oldCtMsgId);

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('no_messages', $result);
        $this->assertNull($this->getAdminReplyTo($this->testUserId));
    }
}
