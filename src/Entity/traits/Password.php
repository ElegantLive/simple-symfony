<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/23
 * Time: 14:41
 */
namespace App\Entity\Traits;

trait Password {
    /**
     * Hash a plaintext password.
     *
     * This used to be md5($secret . 'doSomethingElse' . $rand) with the salt
     * coming from rand(10000000, 99999999) and stored in a `rand` column. That
     * was three problems at once: md5 is not a password hash, the "pepper" was a
     * literal in the source (so it protected nothing once the repository was
     * readable), and the salt came from a non-cryptographic PRNG over a
     * 90-million-value space.
     *
     * password_hash() generates a cryptographically random salt and embeds it in
     * the returned string, so no separate salt is needed and the `rand` column is
     * gone - nothing else used it.
     *
     * PASSWORD_DEFAULT resolves to bcrypt on this PHP (7.3) and costs ~190ms.
     * Deliberately not pinning a cost: leaving it as PASSWORD_DEFAULT means a
     * future PHP that raises the default is handled by password_needs_rehash()
     * instead of requiring a code change. Tuning the cost from the environment is
     * not possible here - an entity has no constructor injection - so that would
     * mean moving this into a service.
     */
    public function hashSecret (string $secret): string
    {
        return password_hash($secret, PASSWORD_DEFAULT);
    }

    /**
     * Verify a plaintext password against a stored hash.
     *
     * password_verify() is not affected by the byte-at-a-time timing of a plain
     * string comparison, so this also removes the need for the loose `!=` the
     * caller used to do.
     */
    public function verifySecret (string $secret, ?string $hash): bool
    {
        if (null === $hash || '' === $hash) return false;

        return password_verify($secret, $hash);
    }
}
