<?php

namespace App\Encryption;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Encrypts strings with XChaCha20-Poly1305 under the data key of the contact details of speakers.
 *
 * A value is stored as [1-byte version][16-byte id of the data key][24-byte nonce][ciphertext and 16-byte tag],
 * its version and key id being authenticated as associated data.
 */
#[Autoconfigure(public: true)]
final class EncryptedStringType extends Type
{
    public const NAME = 'encrypted_string';

    private const SCOPE = 'speaker.contact';
    private const VERSION = "\x01";
    private const HEADER_BYTES = 17;
    private const NONCE_BYTES = \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

    public function __construct(
        private readonly DataKeyStore $dataKeys,
    ) {
    }

    /**
     * Doctrine instantiates the types of its configuration without arguments, so this one is registered when the kernel boots.
     *
     * The registry is global: a rebooted kernel replaces the type of the previous container.
     */
    public function register(): void
    {
        $types = Type::getTypeRegistry();
        $types->has(self::NAME) ? $types->override(self::NAME, $this) : $types->register(self::NAME, $this);
    }

    public function convertToDatabaseValue(#[\SensitiveParameter] mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        [$id, $key] = $this->dataKeys->current(self::SCOPE);
        $header = self::VERSION.$id;
        $nonce = random_bytes(self::NONCE_BYTES);

        return $header.$nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($value, $header, $nonce, $key);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = \is_resource($value) ? stream_get_contents($value) : $value;

        if (!str_starts_with($value, self::VERSION) || \strlen($value) < self::HEADER_BYTES + self::NONCE_BYTES + \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
            throw new ValueNotConvertible('The stored value is not an encrypted string.');
        }

        $header = substr($value, 0, self::HEADER_BYTES);
        $key = $this->dataKeys->get(self::SCOPE, substr($header, 1));
        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($value, self::HEADER_BYTES + self::NONCE_BYTES), $header, substr($value, self::HEADER_BYTES, self::NONCE_BYTES), $key);

        if (false === $plaintext) {
            throw new ValueNotConvertible('The stored value cannot be decrypted.');
        }

        return $plaintext;
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::BINARY;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBlobTypeDeclarationSQL($column);
    }
}
