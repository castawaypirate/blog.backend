<?php

/**
 * Phase 3: Follow-Up — User Previously Sent PK Only (DB has public key)
 * 
 * Tests scenarios 3.1.1–3.3.2 from the maze scenario matrix.
 * DB is seeded with a previous challenge containing a public key.
 */
class Phase3FollowUpPKTest extends MazeTestCase
{
    // =================================================================
    // 3.1 — DB has PK✓, user sends CT
    // =================================================================

    /** @test 3.1.1 — CT✓ + DB:PK✓ → SUCCESS */
    public function testDBPKGoodSendCTGoodSuccess(): void
    {
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('pending_message', $this->pkGood(), $oldPkMsgId, null);

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

    /** @test 3.1.2 — CT~id + DB:PK✓ → identity failed */
    public function testDBPKGoodSendCTWrongIdIdentityFailed(): void
    {
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('pending_message', $this->pkGood(), $oldPkMsgId, null);

        $this->injectMessage($this->testUserId, $this->adminId, $this->ctWrongId());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Identity verification failed! The decrypted content doesn't match your account username. Make sure you encrypt exactly your username—the one you use on this site!",
            $reply
        );
    }

    /** @test 3.1.3 — CT✗ + DB:PK✓ → unreadable message */
    public function testDBPKGoodSendCTBadUnreadable(): void
    {
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('pending_message', $this->pkGood(), $oldPkMsgId, null);

        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Your encrypted message is unreadable—I couldn't decrypt it. Re-encrypt your username using my public key and try again!",
            $reply
        );
    }

    // =================================================================
    // 3.2 — DB has PK✗, user sends CT
    // =================================================================

    /** @test 3.2.1 — CT✓ + DB:PK✗ → broken key error */
    public function testDBPKBadSendCTGoodBrokenKey(): void
    {
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());
        $this->seedChallengeState('pending_message', $this->pkBad(), $oldPkMsgId, null);

        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I couldn't encrypt my reply with your Public Key. It looks broken—generate a new one or be certain that you correctly copied your public key to here.",
            $reply
        );
    }

    /** @test 3.2.2 — CT~id + DB:PK✗ → identity failed + broken key */
    public function testDBPKBadSendCTWrongIdBothBad(): void
    {
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());
        $this->seedChallengeState('pending_message', $this->pkBad(), $oldPkMsgId, null);

        $this->injectMessage($this->testUserId, $this->adminId, $this->ctWrongId());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Identity verification failed and your Public Key looks broken too. Make sure you encrypt your actual username, and generate a new key or be certain that you correctly copied it here!",
            $reply
        );
    }

    /** @test 3.2.3 — CT✗ + DB:PK✗ → both bad */
    public function testDBPKBadSendCTBadBothBad(): void
    {
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());
        $this->seedChallengeState('pending_message', $this->pkBad(), $oldPkMsgId, null);

        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "Your encrypted message is unreadable and your Public Key looks broken too. Double-check both on the cryptography site!",
            $reply
        );
    }

    // =================================================================
    // 3.3 — DB has PK (any), user sends new PK (override)
    // =================================================================

    /** @test 3.3.1 — PK✓ override → [key-ovr] new key accepted */
    public function testPKOverrideGoodAccepted(): void
    {
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());
        $this->seedChallengeState('pending_message', $this->pkBad(), $oldPkMsgId, null);

        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingMessageHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "New key, who dis? Just kidding—I've swapped your old key for this one. Still waiting on that encrypted message, though!",
            $reply
        );
    }

    /** @test 3.3.2 — PK✗ override → [key-ovr] new key also bad */
    public function testPKOverrideBadStillBad(): void
    {
        $oldPkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());
        $this->seedChallengeState('pending_message', $this->pkGood(), $oldPkMsgId, null);

        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('sendMissingMessageHint', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've swapped your old key for this new one, but it doesn't look right either—generate a new one or make sure you copied it correctly. Still waiting on that encrypted message too!",
            $reply
        );
    }
}
