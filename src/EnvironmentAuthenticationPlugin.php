<?php
/**
 * Composer Environment Authentication Plugin.
 *
 * @package aysnc/composer-env-auth
 */

declare( strict_types=1 );

namespace Aysnc\ComposerEnvironmentAuthentication;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PreFileDownloadEvent;

/**
 * Environment Authentication Plugin for Composer.
 * This plugin automatically applies authentication configuration from environment variables
 * to Composer repositories based on configuration in composer.json.
 */
class EnvironmentAuthenticationPlugin implements PluginInterface, EventSubscriberInterface {
	/**
	 * @var Composer The Composer instance
	 */
	private Composer $composer;

	/**
	 * @var IOInterface The IO interface for user interaction
	 */
	private IOInterface $io;

	/**
	 * @var EnvironmentLoader The environment variable loader
	 */
	private EnvironmentLoader $environmentLoader;

	/**
	 * Activates the plugin when Composer is initialized.
	 * This method is called when the plugin is activated and sets up the necessary
	 * dependencies and applies environment-based authentication immediately.
	 *
	 * @param Composer    $composer The Composer instance
	 * @param IOInterface $io       The IO interface for user interaction
	 */
	public function activate( Composer $composer, IOInterface $io ): void {
		$this->composer          = $composer;
		$this->io                = $io;
		$this->environmentLoader = new EnvironmentLoader();

		// Apply authentication immediately on activation to ensure it's available
		// for any subsequent repository operations
		$this->applyEnvironmentAuthentication();
	}

	/**
	 * Deactivates the plugin.
	 * Currently, no cleanup is needed during deactivation.
	 *
	 * @param Composer    $composer The Composer instance
	 * @param IOInterface $io       The IO interface for user interaction
	 */
	public function deactivate( Composer $composer, IOInterface $io ): void {
		// Nothing to deactivate.
	}

	/**
	 * Uninstalls the plugin.
	 * Currently, no cleanup is needed during uninstallation.
	 *
	 * @param Composer    $composer The Composer instance
	 * @param IOInterface $io       The IO interface for user interaction
	 */
	public function uninstall( Composer $composer, IOInterface $io ): void {
		// Nothing to uninstall.
	}

	/**
	 * Returns an array of event names this subscriber wants to listen to.
	 *
	 * @return array The event names to listen to
	 */
	public static function getSubscribedEvents(): array {
		return [
			// Subscribe to the INIT event to ensure authentication is applied
			PluginEvents::INIT => 'onInit',
		];
	}

	/**
	 * Handles the INIT event from Composer.
	 * This serves as a backup to ensure authentication is applied even if
	 * the activate method didn't complete successfully.
	 */
	public function onInit(): void {
		// Apply authentication on init event as a backup mechanism
		// in case the activate method didn't complete successfully
		$this->applyEnvironmentAuthentication();
	}

	/**
	 * Applies environment-based authentication to Composer's configuration.
	 * This method reads all authentication configurations from composer.json
	 * and applies them to Composer's auth configuration using environment variables.
	 */
	private function applyEnvironmentAuthentication(): void {
		// Retrieve all authentication configurations from composer.json
		// and apply them to Composer's auth configuration
		$authenticationConfigs = $this->getAllAuthenticationConfigs();

		// Process each domain's authentication configuration
		foreach ( $authenticationConfigs as $domain => $authConfig ) {
			if ( is_string( $authConfig ) ) {
				// Simple token authentication: config value is the environment variable name
				$token = $this->environmentLoader->getEnvironmentVariable( $authConfig );
				if ( $token ) {
					$this->setComposerAuthentication( $domain, null, $token );
				}
			} elseif ( is_array( $authConfig ) && isset( $authConfig['username'] ) && isset( $authConfig['password'] ) ) {
				// HTTP Basic authentication: config contains username and password env var names
				$username = $this->environmentLoader->getEnvironmentVariable( $authConfig['username'] );
				$password = $this->environmentLoader->getEnvironmentVariable( $authConfig['password'] );

				// Only apply if both credentials are available
				if ( $username && $password ) {
					$this->setComposerAuthentication( $domain, $username, $password );
				}
			}
		}
	}

	/**
	 * Finds authentication configuration for a specific URL.
	 * Searches through composer.json repositories to find a matching
	 * authentication configuration for the given URL.
	 *
	 * @param string $url The URL to find authentication config for
	 *
	 * @return array|null The authentication config array or null if not found
	 */
	private function findAuthenticationConfigForUrl( string $url ): ?array {
		// Read composer.json directly to find repository configurations
		$composerJsonPath = getcwd() . '/composer.json';
		if ( ! file_exists( $composerJsonPath ) ) {
			return null;
		}

		$composerJsonContent = file_get_contents( $composerJsonPath );
		$composerData        = json_decode( $composerJsonContent, true );

		if ( ! isset( $composerData['repositories'] ) ) {
			return null;
		}

		$repositories = $composerData['repositories'];

		foreach ( $repositories as $repositoryData ) {
			if ( isset( $repositoryData['url'] ) && isset( $repositoryData['options']['aysnc/env-auth'] ) ) {
				$repositoryUrl = $repositoryData['url'];

				if ( $this->urlMatches( $url, $repositoryUrl ) ) {
					return [
						'config'        => $repositoryData['options']['aysnc/env-auth'],
						'repositoryUrl' => $repositoryUrl,
					];
				}
			}
		}

		return null;
	}

	/**
	 * Checks if two URLs match by comparing their hostnames.
	 *
	 * @param string $downloadUrl   The download URL to check
	 * @param string $repositoryUrl The repository URL to compare against
	 *
	 * @return bool True if the hostnames match, false otherwise
	 */
	private function urlMatches( string $downloadUrl, string $repositoryUrl ): bool {
		$downloadHost   = parse_url( $downloadUrl, PHP_URL_HOST );
		$repositoryHost = parse_url( $repositoryUrl, PHP_URL_HOST );

		return $downloadHost === $repositoryHost;
	}

	/**
	 * Applies authentication configuration to a stream context.
	 * This method modifies the provided context array to include the appropriate
	 * authentication headers based on the configuration type (token or basic auth).
	 *
	 * @param array  $context              The stream context to modify (passed by reference)
	 * @param array  $authenticationConfig The authentication configuration
	 * @param string $url                  The URL being authenticated for
	 */
	private function applyAuthenticationToContext( array &$context, array $authenticationConfig, string $url ): void {
		$environmentConfig = $authenticationConfig['config'];

		if ( is_string( $environmentConfig ) ) {
			// Simple token format: "GITHUB_TOKEN"
			$this->applyTokenAuthentication( $context, $environmentConfig, $url );
		} elseif ( is_array( $environmentConfig ) ) {
			// Username/password format: {"username": "USER_VAR", "password": "PASS_VAR"}
			$this->applyBasicAuthentication( $context, $environmentConfig, $url );
		}
	}

	/**
	 * Applies token-based authentication to a stream context.
	 * Determines the appropriate token header format based on the hostname
	 * (GitHub uses 'token', GitLab uses 'PRIVATE-TOKEN', others use 'Bearer').
	 *
	 * @param array  $context       The stream context to modify (passed by reference)
	 * @param string $tokenVariable The environment variable name containing the token
	 * @param string $url           The URL being authenticated for
	 */
	private function applyTokenAuthentication( array &$context, string $tokenVariable, string $url ): void {
		$token = $this->environmentLoader->getEnvironmentVariable( $tokenVariable );

		if ( ! $token ) {
			return;
		}

		$host = parse_url( $url, PHP_URL_HOST );

		if ( $host === 'github.com' || str_contains( $host, 'github' ) ) {
			// GitHub OAuth token
			$context['options']['http']['header'][] = "Authorization: token {$token}";
		} elseif ( $host === 'gitlab.com' || str_contains( $host, 'gitlab' ) ) {
			// GitLab private token
			$context['options']['http']['header'][] = "PRIVATE-TOKEN: {$token}";
		} else {
			// Generic bearer token
			$context['options']['http']['header'][] = "Authorization: Bearer {$token}";
		}
	}

	/**
	 * Applies HTTP Basic authentication to a stream context.
	 * Reads username and password from environment variables and adds
	 * the appropriate Authorization header with Base64-encoded credentials.
	 *
	 * @param array  $context           The stream context to modify (passed by reference)
	 * @param array  $environmentConfig The environment configuration containing username/password variable names
	 * @param string $url               The URL being authenticated for
	 */
	private function applyBasicAuthentication( array &$context, array $environmentConfig, string $url ): void {
		$username = null;
		$password = null;

		if ( isset( $environmentConfig['username'] ) ) {
			$username = $this->environmentLoader->getEnvironmentVariable( $environmentConfig['username'] );
		}

		if ( isset( $environmentConfig['password'] ) ) {
			$password = $this->environmentLoader->getEnvironmentVariable( $environmentConfig['password'] );
		}

		if ( ! $username || ! $password ) {
			return;
		}

		$authentication                         = base64_encode( "{$username}:{$password}" );
		$context['options']['http']['header'][] = "Authorization: Basic {$authentication}";
	}

	/**
	 * Retrieves all authentication configurations from composer.json.
	 * Parses the composer.json file and extracts all repository authentication
	 * configurations, organizing them by domain.
	 *
	 * @return array An associative array of domain => auth_config pairs
	 */
	private function getAllAuthenticationConfigs(): array {
		// Read composer.json directly
		$composerJsonPath = getcwd() . '/composer.json';
		if ( ! file_exists( $composerJsonPath ) ) {
			return [];
		}

		$composerJsonContent = file_get_contents( $composerJsonPath );
		$composerData        = json_decode( $composerJsonContent, true );

		if ( ! isset( $composerData['repositories'] ) ) {
			return [];
		}

		$authConfigs = [];
		foreach ( $composerData['repositories'] as $repositoryData ) {
			if ( isset( $repositoryData['url'] ) && isset( $repositoryData['options']['aysnc/env-auth'] ) ) {
				$domain                 = parse_url( $repositoryData['url'], PHP_URL_HOST );
				$authConfigs[ $domain ] = $repositoryData['options']['aysnc/env-auth'];
			}
		}

		return $authConfigs;
	}

	/**
	 * Sets authentication configuration in Composer's config.
	 * Configures Composer's authentication settings based on the domain and
	 * authentication type (basic auth vs. token-based auth).
	 *
	 * @param string      $domain          The domain to set authentication for
	 * @param string|null $username        The username (null for token-based auth)
	 * @param string      $passwordOrToken The password or token value
	 */
	private function setComposerAuthentication( string $domain, ?string $username, string $passwordOrToken ): void {
		$authConfig = [];

		if ( $username ) {
			// Basic authentication
			$authConfig = [
				'http-basic' => [
					$domain => [
						'username' => $username,
						'password' => $passwordOrToken,
					],
				],
			];
		} else {
			// Token authentication - determine type by domain
			if ( $domain === 'github.com' || str_contains( $domain, 'github' ) ) {
				$authConfig = [
					'github-oauth' => [
						$domain => $passwordOrToken,
					],
				];
			} elseif ( $domain === 'gitlab.com' || str_contains( $domain, 'gitlab' ) ) {
				$authConfig = [
					'gitlab-token' => [
						$domain => $passwordOrToken,
					],
				];
			} else {
				// Generic bearer token - use basic auth with token as password
				$authConfig = [
					'http-basic' => [
						$domain => [
							'username' => 'token',
							'password' => $passwordOrToken,
						],
					],
				];
			}
		}

		// Apply authentication using direct config manipulation
		if ( isset( $authConfig['http-basic'] ) ) {
			foreach ( $authConfig['http-basic'] as $domain => $credentials ) {
				// Set the authentication data directly in the config object
				$this->composer->getConfig()->getAuthConfigSource()->addConfigSetting( 'http-basic.' . $domain, $credentials );
			}
		}
	}
}
