<?php

/**
 * Phase 2: Follow-Up — User Previously Sent CT Only (DB has ciphertext)
 * 
 * Tests scenarios 2.1.1–2.4.3 from the maze scenario matrix.
 * DB is seeded with a previous challenge containing ciphertext.
 */
class Phase2FollowUpCTTest extends MazeTestCase
{
    // =================================================================
    // 2.1 — DB has CT✓, user sends PK
    // =================================================================

    /** @test 2.1.1 — DB:CT✓ + PK✓ → SUCCESS */
    public function testDBCTGoodSendPKGoodSuccess(): void
    {
        // Arrange: inject the old CT message and seed challenge state
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->seedChallengeState('pending_key', null, null, $oldCtMsgId);

        // Inject new PK message
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        // Act
        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        // Assert
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

    /** @test 2.1.2 — DB:CT✓ + PK✗ → broken key error */
    public function testDBCTGoodSendPKBadBrokenKey(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->seedChallengeState('pending_key', null, null, $oldCtMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Your secret message decrypted successfully and verified your identity, but I couldn't encrypt my reply with your Public Key because it looks broken! Generate a new key and try again!",
            $reply
        );
    }

    // =================================================================
    // 2.2 — DB has CT~id, user sends PK
    // =================================================================

    /** @test 2.2.1 — DB:CT~id + PK✓ → identity failed */
    public function testDBCTWrongIdSendPKGoodIdentityFailed(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctWrongId());
        $this->seedChallengeState('invalid', null, null, $oldCtMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've received your Public Key (it looks good!), but your encrypted message failed identity verification! The decrypted content doesn't match your username. Make sure you encrypt exactly your username!",
            $reply
        );
    }

    /** @test 2.2.2 — DB:CT~id + PK✗ → identity failed + broken key */
    public function testDBCTWrongIdSendPKBadBothBad(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctWrongId());
        $this->seedChallengeState('invalid', null, null, $oldCtMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Your Public Key looks broken, and your encrypted message fails identity verification! Fix your encrypted username and generate a new key.",
            $reply
        );
    }

    // =================================================================
    // 2.3 — DB has CT✗, user sends PK
    // =================================================================

    /** @test 2.3.1 — DB:CT✗ + PK✓ → unreadable message */
    public function testDBCTBadSendPKGoodUnreadable(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());
        $this->seedChallengeState('invalid', null, null, $oldCtMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've received your Public Key (it looks good!), but your encrypted message is unreadable—I couldn't decrypt it. Re-encrypt your username using my public key and try again!",
            $reply
        );
    }

    /** @test 2.3.2 — DB:CT✗ + PK✗ → both bad */
    public function testDBCTBadSendPKBadBothBad(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());
        $this->seedChallengeState('invalid', null, null, $oldCtMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Your encrypted message is unreadable and your Public Key looks broken too. Double-check both on the cryptography site!",
            $reply
        );
    }

    // =================================================================
    // 2.4 — DB has CT (any), user sends new CT (override, no PK)
    // =================================================================

    /** @test 2.4.1 — CT✓ override → [msg-ovr] missing key hint (identity ok) */
    public function testCTOverrideGoodMissingKeyHint(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());
        $this->seedChallengeState('invalid', null, null, $oldCtMsgId);

        // New CT replaces old — this is the override
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated your secret message (it decrypted successfully and your identity checked out!), but I don't have your Public Key yet! How am I supposed to reply securely?",
            $reply
        );
    }

    /** @test 2.4.2 — CT~id override → [msg-ovr] missing key hint (identity fail) */
    public function testCTOverrideWrongIdMissingKeyHint(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->seedChallengeState('pending_key', null, null, $oldCtMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->ctWrongId());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated your secret message; it decrypted successfully, but identity verification failed! Also, I still don't have your Public Key to reply securely anyway!",
            $reply
        );
    }

    /** @test 2.4.3 — CT✗ override → missing key hint (not decryptable, no prefix) */
    public function testCTOverrideBadMissingKeyHint(): void
    {
        $oldCtMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->seedChallengeState('pending_key', null, null, $oldCtMsgId);

        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingKeyHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        // Per scenario note: isOverride=true + isDecryptable=false → intentionally uses the override variant
        $this->assertSame(
            "I've updated your secret message, but it is unreadable. Also, I don't have your Public Key yet!",
            $reply
        );
    }
}
