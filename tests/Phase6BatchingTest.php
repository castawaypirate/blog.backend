<?php

/**
 * Phase 6: Multiple Messages in Same Batch (Before Processor Runs)
 * 
 * Tests scenarios 6.1–6.8.4 from the maze scenario matrix.
 * Tests the "last wins" batching logic — scan loop iterates ASC,
 * each match overwrites the previous.
 */
class Phase6BatchingTest extends MazeTestCase
{
    // =================================================================
    // 6.1–6.7 — Last-Wins for each type
    // =================================================================

    /** @test 6.1 — CT✗, CT✗, CT✓ → processor uses CT✓ (last one) */
    public function testLastCTWinsGoodLast(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        // CT✓ was used — identity checked out
        $this->assertSame(
            "I decrypted your secret (and your identity checked out!), but I don't have your Public Key yet! How am I supposed to reply securely?",
            $reply
        );
    }

    /** @test 6.2 — CT✓, CT✗ → processor uses CT✗ (last one) */
    public function testLastCTWinsBadLast(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        // CT✗ was used — unreadable
        $this->assertSame(
            "I tried to decrypt your message but it's unreadable. Also, I don't have your Public Key yet!",
            $reply
        );
    }

    /** @test 6.3 — PK✗, PK✓ → processor uses PK✓ (last one) */
    public function testLastPKWinsGoodLast(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingMessageHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Nice key! Now, are you going to send me that encrypted username or what?",
            $reply
        );
    }

    /** @test 6.4 — PK✓, PK✗ → processor uses PK✗ (last one) */
    public function testLastPKWinsBadLast(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingMessageHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "That Public Key doesn't look right—generate a new one or make sure you copied it correctly. And I'm still waiting on that encrypted message!",
            $reply
        );
    }

    /** @test 6.5 — CT✗, PK✗, CT✓, PK✓ → processor uses CT✓+PK✓ → SUCCESS */
    public function testMixedLastWinsSuccess(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());
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

    /** @test 6.6 — CT✓, PK✓, CT✗ → processor uses CT✗+PK✓ → bad ciphertext error */
    public function testGoodThenBadCTLastWins(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Your encrypted message is unreadable—I couldn't decrypt it. Re-encrypt your username using my public key and try again!",
            $reply
        );
    }

    /** @test 6.7 — 3×PK + 4×CT (mixed) → last CT + last PK used */
    public function testManyMixedLastWins(): void
    {
        // Interleave PK and CT messages
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctWrongId());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());  // last PK = good
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());  // last CT = good

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        // Last CT (good) + last PK (good) → SUCCESS
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
    // 6.8 — Multiple with Deletions Before Processor
    // =================================================================

    /** @test 6.8.1 — CT1✗, CT2✓, delete CT2 → CT1✗ is now last → uses CT1 */
    public function testDeleteLastCTFallbackToPrevious(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());  // CT1✗
        $ct2Id = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());  // CT2✓
        $this->deleteMessage($ct2Id);

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        // CT1✗ was used — unreadable
        $this->assertSame(
            "I tried to decrypt your message but it's unreadable. Also, I don't have your Public Key yet!",
            $reply
        );
    }

    /** @test 6.8.2 — CT1✓, CT2✗, delete CT2 → CT1✓ is now last → uses CT1 */
    public function testDeleteBadCTFallbackToGood(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());  // CT1✓
        $ct2Id = $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());  // CT2✗
        $this->deleteMessage($ct2Id);

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        // CT1✓ was used — identity checked out
        $this->assertSame(
            "I decrypted your secret (and your identity checked out!), but I don't have your Public Key yet! How am I supposed to reply securely?",
            $reply
        );
    }

    /** @test 6.8.3 — PK1✓, PK2✗, delete PK2 → PK1✓ is now last → uses PK1 */
    public function testDeleteBadPKFallbackToGood(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());  // PK1✓
        $pk2Id = $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());  // PK2✗
        $this->deleteMessage($pk2Id);

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingMessageHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Nice key! Now, are you going to send me that encrypted username or what?",
            $reply
        );
    }

    /** @test 6.8.4 — CT1, CT2, CT3, delete CT2 → CT3 (last surviving) */
    public function testDeleteMiddleCTLastSurvivingWins(): void
    {
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());  // CT1
        $ct2Id = $this->injectMessage($this->testUserId, $this->adminId, $this->ctWrongId());  // CT2
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());  // CT3✓
        $this->deleteMessage($ct2Id);

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        // CT3✓ is the last surviving → identity checked out
        $this->assertSame(
            "I decrypted your secret (and your identity checked out!), but I don't have your Public Key yet! How am I supposed to reply securely?",
            $reply
        );
    }
}
