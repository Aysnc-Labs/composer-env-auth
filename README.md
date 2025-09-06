# Composer Environment Authentication Plugin

![Maintenance](https://img.shields.io/badge/Actively%20Maintained-yes-green.svg)

> **Streamline your Composer authentication with environment variables**

Are you managing separate `.env` files alongside `auth.json` files for your Composer authentication? This plugin eliminates that redundancy by bringing industry-standard environment variable support directly to Composer's authentication system.

While we await native environment variable support in Composer core, this plugin bridges the gap by allowing you to store all your authentication credentials securely in environment variables — just like every other modern development tool.

## Features

- 🔐 **Unified Authentication**: Store all credentials in environment variables
- 🚀 **Zero Configuration**: Works automatically once installed
- 🎯 **Multiple Auth Types**: Supports GitHub OAuth, GitLab tokens, and HTTP Basic auth
- 📁 **Project-Aware**: Automatically finds your project root and `.env` file
- ⚡ **Lightweight**: Minimal overhead, maximum convenience

## Installation

Install the plugin as a development dependency in your project. Do this before installing any plugins:

```bash
composer require --dev aysnc/composer-env-auth
```

## Quick Start

1. **Add authentication configuration to your `composer.json`:**

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://connect.advancedcustomfields.com",
            "options": {
                "aysnc/env-auth": {
                    "username": "ACF_USERNAME",
                    "password": "ACF_PASSWORD"
                }
            }
        }
    ]
}
```

2. **Set your credentials in your `.env` file:**

```bash
ACF_USERNAME=your_acf_license_key
ACF_PASSWORD=your_acf_license_key
```

3. **Run Composer commands as usual:**

```bash
composer require wpengine/advanced-custom-fields-pro
```

The plugin automatically applies your environment-based authentication!

## Configuration Examples

### HTTP Basic Authentication (Generic)

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://repo.example.com",
            "options": {
                "aysnc/env-auth": {
                    "username": "API_USERNAME",
                    "password": "API_PASSWORD"
                }
            }
        }
    ]
}
```

```bash
# .env
API_USERNAME=your_username
API_PASSWORD=your_password_or_token
```

### GitHub Personal Access Token

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/your-org/private-repo.git",
            "options": {
                "aysnc/env-auth": "GITHUB_TOKEN"
            }
        }
    ]
}
```

```bash
# .env
GITHUB_TOKEN=ghp_your_token_here
```

### GitLab Private Token

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://gitlab.com/your-org/private-repo.git",
            "options": {
                "aysnc/env-auth": "GITLAB_TOKEN"
            }
        }
    ],
    "require": {
        "your-org/private-package": "^1.0",
        "another-org/repo": "dev-main"
    }
}
```

```bash
# .env
GITLAB_TOKEN=glpat-your_token_here
```

### Multiple Repositories

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://connect.advancedcustomfields.com",
            "options": {
                "aysnc/env-auth": {
                    "username": "ACF_USERNAME",
                    "password": "ACF_PASSWORD"
                }
            }
        },
        {
            "type": "composer",
            "url": "https://repo.custom.com",
            "options": {
                "aysnc/env-auth": {
                    "username": "CUSTOM_USER",
                    "password": "CUSTOM_TOKEN"
                }
            }
        },
        {
            "type": "vcs",
            "url": "https://github.com/org-one/repo.git",
            "options": {
                "aysnc/env-auth": "GITHUB_TOKEN"
            }
        },
        {
            "type": "vcs",
            "url": "https://gitlab.com/org-two/repo.git",
            "options": {
                "aysnc/env-auth": "GITLAB_TOKEN"
            }
        }
    ]
}
```

## How It Works

1. **Plugin Activation**: Automatically activated when Composer initializes
2. **Configuration Discovery**: Scans `composer.json` for repositories with `aysnc/env-auth` options
3. **Environment Resolution**: Resolves variables from system environment and `.env` files
4. **Authentication Configuration**: Dynamically configures Composer's authentication system
5. **Seamless Integration**: Standard Composer commands operate with authenticated access

## CI/CD Pipeline Integration

The plugin seamlessly integrates with CI/CD environments where `.env` files may not be present:

```yaml
# GitHub Actions example
env:
  ACF_USERNAME: ${{ secrets.ACF_USERNAME }}
  ACF_PASSWORD: ${{ secrets.ACF_PASSWORD }}
  GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
  GITLAB_TOKEN: ${{ secrets.GITLAB_TOKEN }}

# GitLab CI example
variables:
  ACF_USERNAME: $ACF_USERNAME
  ACF_PASSWORD: $ACF_PASSWORD
  GITHUB_TOKEN: $GITHUB_TOKEN
  GITLAB_TOKEN: $GITLAB_TOKEN
```

System environment variables are automatically detected and utilized without requiring local `.env` files.

## Environment Variable Priority

The plugin resolves environment variables in the following order of precedence:

1. **System environment variables** (`$_ENV`, `$_SERVER`) - highest priority
2. **Variables from `.env` file** in your project root - fallback

This hierarchy ensures that system-level configuration (such as CI/CD pipeline variables) takes precedence over local development settings, while maintaining backwards compatibility with existing `.env` file workflows.

## Supported Authentication Types

| Service | Configuration                                      | Environment Variable Format |
|---------|----------------------------------------------------|----------------------------|
| Generic | `{"username": "USER_VAR", "password": "PASS_VAR"}` | Username/Password or Token |
| GitHub | `"GITHUB_TOKEN"`                                   | Personal Access Token      |
| GitLab | `"GITLAB_TOKEN"`                                   | Private Token              |

## Why This Plugin?

**The Challenge**: Traditional Composer authentication requires maintaining separate `auth.json` files alongside application `.env` configurations, resulting in:

- Fragmented credential management workflows
- Increased risk of credential exposure in version control
- Inconsistent authentication patterns across development tools
- Complex CI/CD configuration requirements

**Our Solution**: This plugin aligns Composer with industry-standard practices by implementing comprehensive environment variable support for authentication workflows.

**Looking Forward**: While we anticipate eventual native environment variable support in Composer core, this plugin provides an immediate, production-ready solution that standardizes authentication practices across modern development workflows.
