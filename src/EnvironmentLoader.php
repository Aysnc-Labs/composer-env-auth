<?php
/**
 * Environment Loader for Composer Authentication Plugin.
 * This class handles loading environment variables from various sources,
 * including system environment variables and .env files.
 *
 * @package aysnc/composer-env-auth
 */

declare( strict_types=1 );

namespace Aysnc\ComposerEnvironmentAuthentication;

use Dotenv\Dotenv;

class EnvironmentLoader {
	/**
	 * @var bool Flag to track whether environment files have been loaded
	 */
	private bool $loaded = false;

	/**
	 * @var array<string, string> Cached environment variables from .env files
	 */
	private array $environmentVariables = [];

	/**
	 * Constructs a new EnvironmentLoader instance.
	 * Automatically loads environment variables from .env files during construction.
	 */
	public function __construct() {
		// Load environment variables immediately upon construction
		$this->loadEnvironment();
	}

	/**
	 * Retrieves an environment variable value by name.
	 * Checks system environment variables first ($_ENV and $_SERVER),
	 * then falls back to variables loaded from .env files.
	 *
	 * @param string $name The name of the environment variable to retrieve
	 *
	 * @return string|null The variable value, or null if not found
	 */
	public function getEnvironmentVariable( string $name ): ?string {
		// First priority: Check system environment variables ($_ENV and $_SERVER)
		// These take precedence over .env file variables
		$value = $_ENV[ $name ] ?? $_SERVER[ $name ] ?? null;

		if ( $value !== null ) {
			return $value;
		}

		// Second priority: Check variables loaded from .env files
		return $this->environmentVariables[ $name ] ?? null;
	}

	/**
	 * Loads environment variables from .env files.
	 * Uses a flag to ensure environment files are only loaded once per instance.
	 */
	private function loadEnvironment(): void {
		// Prevent duplicate loading
		if ( $this->loaded ) {
			return;
		}

		$this->loaded = true;
		$this->loadFromEnvironmentFile();
	}

	/**
	 * Loads variables from the first available .env file.
	 * Searches for .env files in order of preference and loads the first one found.
	 * Variables are stored locally without modifying the global $_ENV array.
	 */
	private function loadFromEnvironmentFile(): void {
		// Find the first available .env file
		$environmentFile = $this->findEnvironmentFile();

		if ( $environmentFile ) {
			try {
				// Create Dotenv instance for the found file
				$dotenv    = Dotenv::createImmutable( dirname( $environmentFile ), basename( $environmentFile ) );
				$variables = $dotenv->load();

				// Store variables locally without modifying global $_ENV
				// This prevents conflicts with existing environment variables
				$this->environmentVariables = array_merge( $this->environmentVariables, $variables );

			} catch ( \Exception $e ) {
				// Silently ignore missing or invalid .env files
				// This allows the plugin to work even without .env files
			}
		}
	}

	/**
	 * Finds the standard .env file in the project.
	 * Looks for the standard .env file in the project root directory.
	 * This keeps authentication configuration simple and predictable.
	 *
	 * @return string|null The path to the .env file, or null if not found
	 */
	private function findEnvironmentFile(): ?string {
		// Determine the project root directory
		$projectRoot = $this->getProjectRoot();

		if ( ! $projectRoot ) {
			return null;
		}

		// Check for the standard .env file
		$envFile = $projectRoot . '/.env';

		if ( file_exists( $envFile ) && is_readable( $envFile ) ) {
			return $envFile;
		}

		return null;
	}

	/**
	 * Determines the project root directory.
	 * Searches upward from the current directory looking for composer.json
	 * to identify the project root. Falls back to the current working directory
	 * if no composer.json is found.
	 *
	 * @return string|null The project root path, or null if it cannot be determined
	 */
	private function getProjectRoot(): ?string {
		$currentDirectory = getcwd();

		if ( ! $currentDirectory ) {
			return null;
		}

		// Search upward through directory tree looking for composer.json
		// This indicates the root of a Composer project
		while ( $currentDirectory && $currentDirectory !== dirname( $currentDirectory ) ) {
			if ( file_exists( $currentDirectory . '/composer.json' ) ) {
				return $currentDirectory;
			}
			// Move up one directory level
			$currentDirectory = dirname( $currentDirectory );
		}

		// Fallback: Use current working directory if no composer.json found
		return getcwd() ?: null;
	}

	/**
	 * Returns all variables loaded from .env files.
	 * This does not include system environment variables from $_ENV or $_SERVER,
	 * only variables that were explicitly loaded from .env files.
	 *
	 * @return array<string, string> Associative array of variable names to values
	 */
	public function getLoadedVariables(): array {
		return $this->environmentVariables;
	}
}
