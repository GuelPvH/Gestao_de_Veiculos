<?php

namespace Tests\Support;

use App\Services\Auth\WebAuthnVerifier;
use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;

/** Synthetic authenticator for protocol tests only. Keys never leave the test process. */
class TestAuthenticator
{
    private \OpenSSLAsymmetricKey $key;

    private string $id;

    public function __construct()
    {
        $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->id = random_bytes(32);
    }

    public function register(array $options, string $origin = 'https://fleet.example.test', bool $verified = true): array
    {
        $details = openssl_pkey_get_details($this->key);
        $cose = MapObject::create()->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))->add(NegativeIntegerObject::create(-2), ByteStringObject::create($details['ec']['x']))->add(NegativeIntegerObject::create(-3), ByteStringObject::create($details['ec']['y']));
        $data = hash('sha256', $options['rp']['id'], true).chr($verified ? 0x45 : 0x41).pack('N', 0).str_repeat("\0", 16).pack('n', strlen($this->id)).$this->id.(string) $cose;
        $attestation = MapObject::create()->add(TextStringObject::create('fmt'), TextStringObject::create('none'))->add(TextStringObject::create('authData'), ByteStringObject::create($data))->add(TextStringObject::create('attStmt'), MapObject::create());

        return $this->payload(['clientDataJSON' => WebAuthnVerifier::encode(json_encode(['type' => 'webauthn.create', 'challenge' => $options['challenge'], 'origin' => $origin], JSON_THROW_ON_ERROR)), 'attestationObject' => WebAuthnVerifier::encode((string) $attestation), 'transports' => ['internal']]);
    }

    public function assert(array $options, string $origin = 'https://fleet.example.test', bool $verified = true, int $counter = 1, string $userHandle = 'fleet-user:1'): array
    {
        $client = json_encode(['type' => 'webauthn.get', 'challenge' => $options['challenge'], 'origin' => $origin], JSON_THROW_ON_ERROR);
        $data = hash('sha256', $options['rpId'], true).chr($verified ? 0x05 : 0x01).pack('N', $counter);
        openssl_sign($data.hash('sha256', $client, true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return $this->payload(['clientDataJSON' => WebAuthnVerifier::encode($client), 'authenticatorData' => WebAuthnVerifier::encode($data), 'signature' => WebAuthnVerifier::encode($signature), 'userHandle' => WebAuthnVerifier::encode($userHandle)]);
    }

    private function payload(array $response): array
    {
        return ['id' => WebAuthnVerifier::encode($this->id), 'rawId' => WebAuthnVerifier::encode($this->id), 'type' => 'public-key', 'response' => $response, 'clientExtensionResults' => []];
    }
}
