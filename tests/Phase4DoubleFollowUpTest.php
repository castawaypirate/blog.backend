<?php

/**
 * Phase 4: Follow-Up — DB Has Both Pieces (Previous Full Attempt Failed)
 * 
 * Tests scenarios 4.1.1–4.3.6 from the maze scenario matrix.
 * DB is seeded with both ciphertext and public key from a previous failed attempt.
 */
class Phase4DoubleFollowUpTest extends MazeTestCase
{
    /**
     * Helper: seed a full previous state with both CT and PK stored in DB.
     * Returns [ctMsgId, pkMsgId].
     */
    private function seedFullState(string $ct, string $pk): array
    {
        $ctMsgId = $this->injectMessage($this->testUserId, $this->adminId, $ct);
        $pkMsgId = $this->injectMessage($this->testUserId, $this->adminId, $pk);
        $this->seedChallengeState('invalid', $pk, $pkMsgId, $ctMsgId);
        return [$ctMsgId, $pkMsgId];
    }

    // =================================================================
    // 4.1 — User sends new CT only (override), DB still has old PK
    // =================================================================

    /** @test 4.1.1 — CT✓ override + DB:PK✓ → [msg-ovr] SUCCESS */
    public function testCTOverrideGoodDBPKGoodSuccess(): void
    {
        $this->seedFullState($this->ctBad(), $this->pkGood());
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

    /** @test 4.1.2 — CT✓ override + DB:PK✗ → [msg-ovr] broken key */
    public function testCTOverrideGoodDBPKBadBrokenKey(): void
    {
        $this->seedFullState($this->ctBad(), $this->pkBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated your secret message (it decrypted successfully and your identity checked out!), but I couldn't encrypt my reply with your Public Key. It looks broken—generate a new one or be certain that you correctly copied your public key to here.",
            $reply
        );
    }

    /** @test 4.1.3 — CT~id override + DB:PK✓ → [msg-ovr] identity failed */
    public function testCTOverrideWrongIdDBPKGoodIdentityFailed(): void
    {
        $this->seedFullState($this->ctGood(), $this->pkGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctWrongId());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated your secret message; it decrypted successfully, but identity verification failed! The decrypted content doesn't match your account username. Make sure you encrypt exactly your username—the one you use on this site!",
            $reply
        );
    }

    /** @test 4.1.4 — CT~id override + DB:PK✗ → [msg-ovr] identity + key broken */
    public function testCTOverrideWrongIdDBPKBadBothBad(): void
    {
        $this->seedFullState($this->ctGood(), $this->pkBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctWrongId());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated your secret message; it decrypted successfully, but identity verification failed! Also, your Public Key looks broken too. Make sure you encrypt your actual username, and generate a new key or be certain that you correctly copied it here!",
            $reply
        );
    }

    /** @test 4.1.5 — CT✗ override + DB:PK✓ → [msg-ovr] can't decrypt */
    public function testCTOverrideBadDBPKGoodCantDecrypt(): void
    {
        $this->seedFullState($this->ctGood(), $this->pkGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated your secret message, but I couldn't decrypt it. However, I have your valid Public Key saved! Re-encrypt your username using my public key and try again!",
            $reply
        );
    }

    /** @test 4.1.6 — CT✗ override + DB:PK✗ → [msg-ovr] both bad */
    public function testCTOverrideBadDBPKBadBothBad(): void
    {
        $this->seedFullState($this->ctGood(), $this->pkBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated your secret message, but it is unreadable, and your Public Key looks broken too. Double-check both on the cryptography site!",
            $reply
        );
    }

    // =================================================================
    // 4.2 — User sends new PK only (override), DB still has old CT
    // =================================================================

    /** @test 4.2.1 — PK✓ override + DB:CT✓ → [key-ovr] SUCCESS */
    public function testPKOverrideGoodDBCTGoodSuccess(): void
    {
        $this->seedFullState($this->ctGood(), $this->pkBad());
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

    /** @test 4.2.2 — PK✓ override + DB:CT~id → [key-ovr] identity failed */
    public function testPKOverrideGoodDBCTWrongIdIdentityFailed(): void
    {
        $this->seedFullState($this->ctWrongId(), $this->pkBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated your Public Key (it looks good!), but your encrypted message still fails identity verification! The decrypted content doesn't match your username. Make sure you encrypt exactly your username!",
            $reply
        );
    }

    /** @test 4.2.3 — PK✓ override + DB:CT✗ → [key-ovr] unreadable CT */
    public function testPKOverrideGoodDBCTBadUnreadable(): void
    {
        $this->seedFullState($this->ctBad(), $this->pkBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated your Public Key (it looks good!), but your encrypted message is unreadable—I couldn't decrypt it. Re-encrypt your username using my public key and try again!",
            $reply
        );
    }

    /** @test 4.2.4 — PK✗ override + DB:CT✓ → [key-ovr] broken key */
    public function testPKOverrideBadDBCTGoodBrokenKey(): void
    {
        $this->seedFullState($this->ctGood(), $this->pkGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated your Public Key, but I couldn't encrypt my reply with it. It looks broken! Fortunately, your secret message is still valid and verified. Generate a new key and try again!",
            $reply
        );
    }

    /** @test 4.2.5 — PK✗ override + DB:CT~id → [key-ovr] key broken + identity failed */
    public function testPKOverrideBadDBCTWrongIdBothBad(): void
    {
        $this->seedFullState($this->ctWrongId(), $this->pkGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated your Public Key, but it looks broken! Additionally, your encrypted message still fails identity verification! Fix your encrypted username and generate a new key.",
            $reply
        );
    }

    /** @test 4.2.6 — PK✗ override + DB:CT✗ → [key-ovr] key broken + CT unreadable */
    public function testPKOverrideBadDBCTBadBothBad(): void
    {
        $this->seedFullState($this->ctBad(), $this->pkGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated your Public Key, but it looks broken, and your encrypted message is unreadable too. Double-check both on the cryptography site!",
            $reply
        );
    }

    // =================================================================
    // 4.3 — User sends both new CT and new PK (double override)
    // =================================================================

    /** @test 4.3.1 — Both override CT✓+PK✓ → [both-ovr] SUCCESS */
    public function testBothOverrideGoodSuccess(): void
    {
        $this->seedFullState($this->ctBad(), $this->pkBad());
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

    /** @test 4.3.2 — Both override CT✓+PK✗ → [both-ovr] key broken */
    public function testBothOverrideCTGoodPKBadBrokenKey(): void
    {
        $this->seedFullState($this->ctBad(), $this->pkGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated both your secret message and your Public Key; your new secret message decrypted successfully and your identity checked out, but I couldn't encrypt my reply with your new Public Key. It looks broken—generate a new one or be certain that you correctly copied your public key to here.",
            $reply
        );
    }

    /** @test 4.3.3 — Both override CT~id+PK✓ → [both-ovr] identity failed */
    public function testBothOverrideCTWrongIdPKGoodIdentityFailed(): void
    {
        $this->seedFullState($this->ctGood(), $this->pkBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctWrongId());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated both your secret message and your Public Key; your new Public Key looks good and your new secret message decrypted successfully, but identity verification failed! The decrypted content doesn't match your account username. Make sure you encrypt exactly your username—the one you use on this site!",
            $reply
        );
    }

    /** @test 4.3.4 — Both override CT~id+PK✗ → [both-ovr] identity + key broken */
    public function testBothOverrideCTWrongIdPKBadBothBad(): void
    {
        $this->seedFullState($this->ctGood(), $this->pkGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctWrongId());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated both your secret message and your Public Key; your new secret message decrypted successfully, but identity verification failed, and your new Public Key looks broken too. Make sure you encrypt your actual username, and generate a new key or be certain that you correctly copied it here!",
            $reply
        );
    }

    /** @test 4.3.5 — Both override CT✗+PK✓ → [both-ovr] CT unreadable */
    public function testBothOverrideCTBadPKGoodUnreadable(): void
    {
        $this->seedFullState($this->ctGood(), $this->pkBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkGood());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated both your secret message and your Public Key; your new Public Key looks good, but I couldn't decrypt your new secret message. Re-encrypt your username using my public key and try again!",
            $reply
        );
    }

    /** @test 4.3.6 — Both override CT✗+PK✗ → [both-ovr] both bad */
    public function testBothOverrideCTBadPKBadBothBad(): void
    {
        $this->seedFullState($this->ctGood(), $this->pkGood());
        $this->injectMessage($this->testUserId, $this->adminId, $this->ctBad());
        $this->injectMessage($this->testUserId, $this->adminId, $this->pkBad());

        $result = $this->mazeProcessor->processUser($this->testUserId, $this->adminId);

        $this->assertSame('processChallenge', $result);
        $reply = $this->getAdminReplyTo($this->testUserId);
        $this->assertSame(
            "I've updated both your secret message and your Public Key, but your new secret message is unreadable and your new Public Key looks broken too. Double-check both on the cryptography site!",
            $reply
        );
    }
}
