<?php

namespace Tests\Unit;

use App\Services\Auth\PasswordVerifier;
use PHPUnit\Framework\TestCase;

class PasswordVerifierTest extends TestCase
{
    public function test_bcrypt_accepts_only_the_complete_password(): void
    {
        $verificador = new PasswordVerifier;
        $senha = str_repeat('a', 72);
        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 4]);
        $this->assertTrue($verificador->verify($senha, $hash));
        $this->assertFalse($verificador->verify($senha.'suffix', $hash));
        $this->assertFalse($verificador->verify('incorreta', $hash));
        $this->assertFalse($verificador->verify("senha\0invalida", $hash));
        $this->assertFalse($verificador->verify('senha', md5('senha')));
    }

    public function test_imported_argon2id_can_be_verified(): void
    {
        if (! in_array('argon2id', password_algos(), true)) {
            $this->markTestSkipped('Runtime sem Argon2id.');
        }
        $senha = 'Senha privada somente em memória 392!';
        $hash = password_hash($senha, PASSWORD_ARGON2ID, ['memory_cost' => 8192, 'time_cost' => 1, 'threads' => 1]);
        $this->assertTrue((new PasswordVerifier)->verify($senha, $hash));
        $this->assertFalse((new PasswordVerifier)->verify('errada',$hash));
    }
}
