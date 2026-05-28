<?php
/**
 * One-time fixture generator for Maze Engine tests.
 * Run: php tests/fixtures/generate_fixtures.php
 * Outputs key files and a payloads.php include file.
 */

$passphrase = 'test_maze_pass';
$dir = __DIR__;

// --- 1. Admin Keypair ---
$adminKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($adminKey, $adminPrivPem);

// Convert PEM private key to DER bytes
$lines = explode("\n", trim($adminPrivPem));
array_shift($lines); // Remove -----BEGIN ...
array_pop($lines);   // Remove -----END ...
$privDer = base64_decode(implode('', $lines));

// Encrypt DER with AES-256-GCM (same format as production)
$salt = random_bytes(16);
$iv   = random_bytes(12);
$aesKey = hash_pbkdf2('sha256', $passphrase, $salt, 100000, 32, true);
$tag = '';
$enc = openssl_encrypt($privDer, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);
$blob = $salt . $iv . $enc . $tag;
$armoredPriv = "-----BEGIN PRIVATE KEY-----\n" . chunk_split(base64_encode($blob), 64) . "-----END PRIVATE KEY-----\n";
file_put_contents("$dir/test-admin-sec.asc", $armoredPriv);

// Admin public key (standard PEM = valid armor for validatePublicKey)
$adminPubPem = openssl_pkey_get_details($adminKey)['key'];
file_put_contents("$dir/test-admin-pub.asc", $adminPubPem);

// --- 2. User Keypair (for PK✓) ---
$userKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

openssl_pkey_export($userKey, $userPrivPem);
file_put_contents("$dir/testuser-sec.asc", $userPrivPem);

$userPubPem = openssl_pkey_get_details($userKey)['key'];
file_put_contents("$dir/test-user-pub.asc", $userPubPem);

// --- 3. Generate encrypted payloads ---
$adminPubRes = openssl_pkey_get_public($adminPubPem);

// CT✓ — 'Alice' encrypted with admin public key
openssl_public_encrypt('Alice', $ctGoodRaw, $adminPubRes, OPENSSL_PKCS1_OAEP_PADDING);
$ctGood = "-----BEGIN CIPHERTEXT-----\n" . chunk_split(base64_encode($ctGoodRaw), 64) . "-----END CIPHERTEXT-----";

// CT~id — 'Bob' encrypted with admin public key (wrong identity)
openssl_public_encrypt('Bob', $ctWrongIdRaw, $adminPubRes, OPENSSL_PKCS1_OAEP_PADDING);
$ctWrongId = "-----BEGIN CIPHERTEXT-----\n" . chunk_split(base64_encode($ctWrongIdRaw), 64) . "-----END CIPHERTEXT-----";

// CT✗ — garbled ciphertext
$ctBad = "-----BEGIN CIPHERTEXT-----\nTkhJUyBJUyBOT1QgVkFMSUQgQ0lQSEVSVEVYVA==\n-----END CIPHERTEXT-----";

// PK✓ — user's public key (already valid PEM format)
$pkGood = trim($userPubPem);

// PK✗ — garbled public key
$pkBad = "-----BEGIN PUBLIC KEY-----\nTkhJUyBJUyBOT1QgQSBWQUxJRCBQVUJMSUMgS0VZ\n-----END PUBLIC KEY-----";

// --- 4. Write payloads.php ---
$payloads = "<?php\n// Auto-generated fixture payloads. Do not edit.\n// Generated: " . date('Y-m-d H:i:s') . "\n\n";
$payloads .= "return [\n";
$payloads .= "    'CT_GOOD' => " . var_export($ctGood, true) . ",\n";
$payloads .= "    'CT_WRONG_ID' => " . var_export($ctWrongId, true) . ",\n";
$payloads .= "    'CT_BAD' => " . var_export($ctBad, true) . ",\n";
$payloads .= "    'PK_GOOD' => " . var_export($pkGood, true) . ",\n";
$payloads .= "    'PK_BAD' => " . var_export($pkBad, true) . ",\n";
$payloads .= "];\n";
file_put_contents("$dir/payloads.php", $payloads);

echo "✓ Generated:\n";
echo "  $dir/test-admin-sec.asc\n";
echo "  $dir/test-admin-pub.asc\n";
echo "  $dir/test-user-pub.asc\n";
echo "  $dir/payloads.php\n";
echo "\nPassphrase: $passphrase\n";
