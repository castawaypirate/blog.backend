<?php

use PHPUnit\Framework\TestCase;

/**
 * Profile-picture upload: validation in UserController and naming/storage in UserService.
 * No database: the user repository is a mock and files go to a temp directory.
 */
class ProfilePicUploadTest extends TestCase
{
    private const USER_ID = 7;

    private string $root;
    private ?string $storedPath = null;
    private UserController $controller;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/blogdot_upload_test_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/uploads', 0755, true);

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findById')->willReturn(new User(self::USER_ID, 'u', 'u@example.com', 'x'));
        $repo->method('updateProfilePic')->willReturnCallback(function ($id, $path) {
            $this->storedPath = $path;
            return true;
        });

        // move_uploaded_file refuses anything that did not arrive over HTTP, so use rename here
        $service = new UserService(
            $repo,
            $this->createMock(MessageRepository::class),
            $this->createMock(LoginAttemptRepository::class),
            'rename',
            $this->root . '/uploads/'
        );
        $this->controller = new UserController($service);
    }

    protected function tearDown(): void
    {
        $_FILES = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->root);
    }

    private function upload(string $content, string $clientName, string $clientType = 'image/png'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, $content);
        $_FILES = ['profilePic' => [
            'name' => $clientName,
            'type' => $clientType,
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($content),
        ]];
        return $this->controller->uploadProfilePic(self::USER_ID);
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    }

    private function filesInUploads(): array
    {
        $found = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root . '/uploads', FilesystemIterator::SKIP_DOTS)) as $f) {
            $found[] = $f->getPathname();
        }
        return $found;
    }

    public function testPhpFileIsRejected(): void
    {
        $result = $this->upload('<?php echo 1;', 'shell.php', 'application/x-php');
        $this->assertFalse($result['success']);
        $this->assertSame([], $this->filesInUploads());
    }

    public function testPhpRenamedToJpgIsRejected(): void
    {
        $result = $this->upload('<?php echo 1;', 'shell.jpg', 'image/jpeg');
        $this->assertFalse($result['success']);
        $this->assertSame([], $this->filesInUploads());
    }

    public function testPhpAppendedToPngIsStoredOnlyAsImageName(): void
    {
        $result = $this->upload($this->png() . '<?php echo 1;', 'a.php.png');
        // real PNG header, so it passes as an image, but the name is still server-generated
        $this->assertTrue($result['success']);
        $this->assertMatchesRegularExpression('#^7/[a-f0-9]{32}\.png$#', $this->storedPath);
    }

    public function testClientFilenameIsIgnored(): void
    {
        $result = $this->upload($this->png(), '../x.png');
        $this->assertTrue($result['success']);
        $this->assertMatchesRegularExpression('#^7/[a-f0-9]{32}\.png$#', $this->storedPath);
        $this->assertFileDoesNotExist($this->root . '/x.png');
        $this->assertFileDoesNotExist($this->root . '/uploads/x.png');
    }

    public function testOversizedFileIsRejected(): void
    {
        $result = $this->upload($this->png() . str_repeat('A', 10000001), 'big.png');
        $this->assertFalse($result['success']);
        $this->assertSame([], $this->filesInUploads());
    }

    public function testValidPngIsStoredUnderRandomName(): void
    {
        $result = $this->upload($this->png(), 'avatar.png');
        $this->assertTrue($result['success']);
        $this->assertMatchesRegularExpression('#^7/[a-f0-9]{32}\.png$#', $this->storedPath);
        $this->assertFileExists($this->root . '/uploads/' . $this->storedPath);
        $this->assertStringNotContainsString('avatar', $this->storedPath);
    }
}
