<?php

namespace App\Services\Auth;

use App\Support\Value;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

class WebAuthnVerifier
{
    public function serializer(): SerializerInterface
    {
        return (new WebauthnSerializerFactory(new AttestationStatementSupportManager([new NoneAttestationStatementSupport])))->create();
    }

    /**
     * @return array{string, string}
     */
    public function settings(): array
    {
        $origin = Value::string(config('fleet.mfa.origin', ''));
        $rp = Value::string(config('fleet.mfa.rp_id', ''));
        $url = parse_url($origin);
        abort_unless(is_array($url) && ($url['scheme'] ?? '') === 'https' && ($url['host'] ?? '') === $rp && ! isset($url['user']) && ! isset($url['pass']) && ! isset($url['query']) && ! isset($url['fragment']) && in_array($url['path'] ?? '', ['', '/'], true), 503, 'MFA_NOT_CONFIGURED');

        return [rtrim($origin, '/'), $rp];
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function verify(array $payload, string $options, string $purpose, ?string $stored = null): CredentialRecord
    {
        [$origin, $rp] = $this->settings();
        $factory = new CeremonyStepManagerFactory;
        $factory->setAllowedOrigins([$origin], false);
        $serializer = $this->serializer();
        $credential = $serializer->deserialize(json_encode($payload, JSON_THROW_ON_ERROR), PublicKeyCredential::class, 'json');
        if ($purpose === 'registration') {
            abort_unless($credential->response instanceof AuthenticatorAttestationResponse, 422);
            $creation = $serializer->deserialize($options, PublicKeyCredentialCreationOptions::class, 'json');
            $record = AuthenticatorAttestationResponseValidator::create($factory->creationCeremony())->check($credential->response, $creation, Value::string($rp));
        } else {
            abort_unless($credential->response instanceof AuthenticatorAssertionResponse && $stored, 422);
            $record = $serializer->deserialize($stored, CredentialRecord::class, 'json');
            $assertion = $serializer->deserialize($options, PublicKeyCredentialRequestOptions::class, 'json');
            abort_unless(hash_equals($record->publicKeyCredentialId, $credential->rawId), 422);
            $record = AuthenticatorAssertionResponseValidator::create($factory->requestCeremony())->check($record, $credential->response, $assertion, Value::string($rp), $record->userHandle);
        }
        abort_unless(hash_equals($record->publicKeyCredentialId, $credential->rawId), 422);

        return $record;
    }

    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
