<?php

namespace App\Services\Auth;

use Illuminate\Auth\AuthenticationException;
use Kreait\Laravel\Firebase\Facades\Firebase;

class FirebaseIdTokenVerifier
{
    /**
     * Verify signature, issuer, audience, expiry, provider, and identity claims
     * through the Firebase Admin SDK.
     *
     * @throws AuthenticationException
     */
    public function verifyGoogle(string $idToken): GoogleIdentity
    {
        try {
            $token = Firebase::auth()->verifyIdToken($idToken, true);
            $claims = $token->claims();

            $firebase = $claims->has('firebase')
                ? $claims->get('firebase')
                : null;
            $provider = is_array($firebase)
                ? ($firebase['sign_in_provider'] ?? null)
                : null;
            $subject = $claims->has('sub') ? $claims->get('sub') : null;
            $email = $claims->has('email') ? $claims->get('email') : null;
            $emailVerified = $claims->has('email_verified')
                ? $claims->get('email_verified')
                : false;
            $name = $claims->has('name') ? $claims->get('name') : null;
            $picture = $claims->has('picture') ? $claims->get('picture') : null;

            if (
                $provider !== 'google.com'
                || ! is_string($subject)
                || $subject === ''
                || ! is_string($email)
                || ! filter_var($email, FILTER_VALIDATE_EMAIL)
                || $emailVerified !== true
            ) {
                throw new AuthenticationException('Invalid Google identity token.');
            }

            $resolvedName = is_string($name) && trim($name) !== ''
                ? trim($name)
                : str($email)->before('@')->replace(['.', '_'], ' ')->title()->toString();
            $avatarUrl = is_string($picture)
                && str_starts_with($picture, 'https://')
                && strlen($picture) <= 2048
                    ? $picture
                    : null;

            return new GoogleIdentity(
                subject: $subject,
                email: mb_strtolower($email),
                name: $resolvedName,
                avatarUrl: $avatarUrl,
            );
        } catch (AuthenticationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new AuthenticationException('Invalid or expired Google identity token.');
        }
    }
}
