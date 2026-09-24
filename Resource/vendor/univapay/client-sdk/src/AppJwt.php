<?php

declare(strict_types=1);

/*
 * Hand-authored SDK extension kept OUTSIDE the generated files.
 *
 * APIMatic regenerates the client and the Api classes on every build, so any
 * logic injected into those files risks a merge conflict whenever the spec
 * changes nearby. Keeping the decoding here -- in a file APIMatic never
 * generates -- means regeneration can never conflict with it. The generated
 * client only delegates to the two helpers below.
 */

namespace UnivaPay;

/**
 * Reads the context a UnivaPay app token was issued for out of its JWT.
 *
 * A store-level app token carries both a `merchant_id` and a `store_id` claim;
 * a merchant-level token carries only `merchant_id`.
 *
 * Decoding only reads the payload segment -- it does **not** verify the
 * signature, which is deliberate. The value is the caller's own credential,
 * already trusted by virtue of being configured on the client; nothing here is
 * an authorization decision. Never use these values to authenticate a third
 * party's token.
 */
final class AppJwt
{
    /**
     * Matches the canonical 8-4-4-4-12 hexadecimal UUID form.
     *
     * Anchored with `\A` and `\z` rather than `^` and `$`: in PCRE `$` also matches
     * immediately before a trailing newline, so `"<uuid>\n"` would slip through and
     * be returned with the newline still attached, and `^` would match at every
     * line start if anyone ever added the `m` modifier. These two anchors mean the
     * whole string must be the UUID, whatever modifiers are set.
     */
    private const UUID_PATTERN =
        '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i';

    private function __construct()
    {
    }

    /**
     * Decodes the payload segment of a JWT without verifying its signature.
     *
     * @param string|null $jwtToken The JWT to decode.
     *
     * @return array<string, mixed>|null The decoded claims, or null unless the
     *         token is a well-formed three-segment JWT whose payload segment is
     *         base64url-encoded JSON describing an object.
     */
    public static function decodePayload(?string $jwtToken): ?array
    {
        if ($jwtToken === null || $jwtToken === '') {
            return null;
        }
        $segments = explode('.', $jwtToken);
        if (count($segments) !== 3) {
            return null;
        }
        // base64_decode in strict mode rejects any character outside the
        // alphabet, so translate base64url to base64 and pad first.
        $base64 = strtr($segments[1], '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }
        $json = base64_decode($base64, true);
        if ($json === false) {
            return null;
        }
        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * Reads a claim from a JWT payload and returns it only if it is a UUID.
     *
     * Anything else -- claim absent, null, not a string, or a string that is not
     * a canonical UUID -- yields null, so a caller never has to distinguish "not
     * set" from "could not decode".
     *
     * @param string|null $jwtToken The JWT to decode.
     * @param string      $claim    Name of the claim to read.
     *
     * @return string|null The claim value as a UUID string, or null.
     */
    public static function readUuidClaim(?string $jwtToken, string $claim): ?string
    {
        $payload = self::decodePayload($jwtToken);
        if ($payload === null) {
            return null;
        }
        $value = $payload[$claim] ?? null;
        if (!is_string($value) || preg_match(self::UUID_PATTERN, $value) !== 1) {
            return null;
        }

        return $value;
    }

    /**
     * Asserts that a store id was resolvable from the configured app token.
     *
     * Used by the store-scoped convenience calls on the client, which take no
     * $storeId argument. It lives here, beside the claim reader, so the message
     * stays in a file APIMATIC never regenerates.
     *
     * The message deliberately says nothing about the token itself: the
     * credential and its claims must never reach an error message or a log. A
     * merchant-level token arriving here is not a broken token -- it is simply
     * not scoped to a store.
     *
     * @param string|null $storeId The store id read from the token, or null.
     *
     * @return string $storeId, when it is present.
     *
     * @throws \RuntimeException When $storeId is null.
     */
    public static function requireStoreId(
        ?string $storeId,
        string $shortcut,
        string $explicit
    ): string {
        if ($storeId === null) {
            throw new \RuntimeException(
                $shortcut . ' requires a store-level App Token: the configured token '
                . 'carries no usable "store_id" claim. Use a store-level App Token, or call '
                . $explicit . ' with an explicit store id.'
            );
        }

        return $storeId;
    }

    /**
     * Asserts that a merchant id was resolvable from the configured app token.
     *
     * The store guard's sibling, for the one shortcut whose endpoint is scoped
     * by both ids (listSubscriptionCharges). Every app token carries a
     * `merchant_id`, so unlike the store guard this is not a case callers are
     * expected to hit: it fires only for a token that could not be decoded at
     * all, and exists so that failure surfaces locally instead of as a request
     * to /merchants//... .
     *
     * @param string|null $merchantId The merchant id read from the token.
     * @param string      $shortcut   The calling shortcut's signature.
     * @param string      $explicit   The explicit-ids call to fall back to.
     *
     * @return string $merchantId, when it is present.
     *
     * @throws \RuntimeException When it is not.
     */
    public static function requireMerchantId(
        ?string $merchantId,
        string $shortcut,
        string $explicit
    ): string {
        if ($merchantId === null) {
            // Same rule as the store guard: nothing about the token or its claims.
            throw new \RuntimeException(
                $shortcut . ' requires an App Token carrying a merchant: the configured token '
                . 'carries no usable "merchant_id" claim. Use a valid App Token, or call '
                . $explicit . ' with an explicit merchant id.'
            );
        }

        return $merchantId;
    }
}
