<?php

/**
 * Compatibility shims for NC APIs removed after ownCloud 7 / NC 25.
 * Loaded by bootstrap.php before Pico.php so the old static calls resolve.
 */

// ── Global namespace ──────────────────────────────────────────────────────────
namespace {
	// OC_Log (removed; constants used extensively in Pico.php)
	if (!class_exists('OC_Log', false)) {
		class OC_Log {
			const DEBUG = 0;
			const INFO  = 1;
			const WARN  = 2;
			const ERROR = 3;
			public static function write(string $app, string $msg, int $level): void {}
		}
	}

	// pico_log() — no-op replacement for \OCP\Util::writeLog() calls in Pico.php
	if (!function_exists('pico_log')) {
		function pico_log(): void {}
	}

}

// ── OCP namespace shims ───────────────────────────────────────────────────────
namespace OCP {
	// OCP\App::isEnabled — removed in NC26+
	if (!class_exists('OCP\App', false)) {
		class App {
			public static function isEnabled(string $appId): bool {
				try {
					return \OCP\Server::get(\OCP\IAppManager::class)->isInstalled($appId);
				} catch (\Throwable) {
					return false;
				}
			}
		}
	}

	// OCP\User — removed in NC26+
	if (!class_exists('OCP\User', false)) {
		class User {
			public static function getUser(): string {
				try {
					return \OCP\Server::get(\OCP\IUserSession::class)->getUser()?->getUID() ?? '';
				} catch (\Throwable) {
					return '';
				}
			}
			public static function getDisplayName(string $uid): string {
				try {
					return \OCP\Server::get(\OCP\IUserManager::class)->get($uid)?->getDisplayName() ?? $uid;
				} catch (\Throwable) {
					return $uid;
				}
			}
		}
	}

	// OCP\Config — removed in NC26+
	if (!class_exists('OCP\Config', false)) {
		class Config {
			/** @param mixed $default */
			public static function getSystemValue(string $key, $default = ''): mixed {
				try {
					return \OCP\Server::get(\OCP\IConfig::class)->getSystemValue($key, $default);
				} catch (\Throwable) {
					return $default;
				}
			}
			public static function getUserValue(string $uid, string $app, string $key, string $default = ''): string {
				try {
					return \OCP\Server::get(\OCP\IConfig::class)->getUserValue($uid, $app, $key, $default);
				} catch (\Throwable) {
					return $default;
				}
			}
		}
	}
}
