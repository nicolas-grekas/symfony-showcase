<?php

namespace App\Encryption;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Keeps the data keys of the encrypted columns in a table, wrapped by a master key read from the environment.
 *
 * The newest key of a scope encrypts its new values; each value records the id of the key that encrypted it.
 * A key is wrapped with XChaCha20-Poly1305 and bound to its id and scope as associated data,
 * so that a row edited or copied under another id or scope cannot be unwrapped.
 */
#[AsDoctrineListener(event: ToolEvents::postGenerateSchema)]
final class DataKeyStore implements ResetInterface
{
    private const TABLE = 'data_keys';
    private const NONCE_BYTES = \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

    /**
     * @var array<string, array<string, string>> Unwrapped data keys by scope and id
     */
    private array $keys = [];

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%env(base64:ENCRYPTION_MASTER_KEY)%')]
        #[\SensitiveParameter]
        private readonly string $masterKey,
    ) {
    }

    /**
     * Not remembered between two calls: a key created in a transaction that is rolled back must not encrypt anything else.
     *
     * @return array{string, string} The id of the newest data key of the scope, created on first use, and the key
     */
    public function current(string $scope): array
    {
        $row = $this->connection->fetchAssociative('SELECT id, wrapped_key FROM '.self::TABLE.' WHERE scope = ? ORDER BY id DESC LIMIT 1', [$scope]);

        if (!$row) {
            return $this->create($scope);
        }

        $id = self::bytes($row['id']);

        return [$id, $this->keys[$scope][$id] ??= $this->unwrap($id, $scope, self::bytes($row['wrapped_key']))];
    }

    public function get(string $scope, string $id): string
    {
        if (isset($this->keys[$scope][$id])) {
            return $this->keys[$scope][$id];
        }

        $wrapped = $this->connection->fetchOne('SELECT wrapped_key FROM '.self::TABLE.' WHERE id = ?', [$id], [ParameterType::BINARY]);

        if (false === $wrapped) {
            throw new \RuntimeException(\sprintf('The data key "%s" does not exist.', bin2hex($id)));
        }

        return $this->keys[$scope][$id] = $this->unwrap($id, $scope, self::bytes($wrapped));
    }

    public function reset(): void
    {
        array_walk_recursive($this->keys, static fn (string &$key) => sodium_memzero($key));
        $this->keys = [];
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $table = Table::editor()
            ->setUnquotedName(self::TABLE)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::BINARY)->setLength(16)->setFixed(true)->create(),
                Column::editor()->setUnquotedName('scope')->setTypeName(Types::STRING)->setLength(100)->create(),
                Column::editor()->setUnquotedName('wrapped_key')->setTypeName(Types::BLOB)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(Index::editor()->setUnquotedName(self::TABLE.'_scope_idx')->setUnquotedColumnNames('scope', 'id')->create())
            ->create();

        $event->setSchema($event->getSchema()->edit()->addTable($table)->create());
    }

    /**
     * @return array{string, string}
     */
    private function create(string $scope): array
    {
        $id = Uuid::v7()->toBinary();
        $key = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
        $nonce = random_bytes(self::NONCE_BYTES);

        $this->connection->insert(self::TABLE, [
            'id' => $id,
            'scope' => $scope,
            'wrapped_key' => $nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($key, $id.$scope, $nonce, $this->masterKey),
        ], ['id' => ParameterType::BINARY, 'wrapped_key' => ParameterType::BINARY]);

        return [$id, $this->keys[$scope][$id] = $key];
    }

    private function unwrap(string $id, string $scope, string $wrapped): string
    {
        $key = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($wrapped, self::NONCE_BYTES), $id.$scope, substr($wrapped, 0, self::NONCE_BYTES), $this->masterKey);

        if (false === $key) {
            throw new \RuntimeException(\sprintf('The data key "%s" cannot be unwrapped for the "%s" scope.', bin2hex($id), $scope));
        }

        return $key;
    }

    /**
     * @param string|resource $value
     */
    private static function bytes(mixed $value): string
    {
        return \is_resource($value) ? stream_get_contents($value) : $value;
    }
}
