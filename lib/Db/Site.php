<?php

declare(strict_types=1);

namespace OCA\FilesPicoCMS\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int    getId()
 * @method string getUid()
 * @method string getSite()
 * @method string getPath()
 * @method string getGid()
 * @method void   setUid(string $uid)
 * @method void   setSite(string $site)
 * @method void   setPath(string $path)
 * @method void   setGid(string $gid)
 */
class Site extends Entity {
	protected string $uid  = '';
	protected string $site = '';
	protected string $path = '';
	protected string $gid  = '';

	public function __construct() {
		$this->addType('uid',  'string');
		$this->addType('site', 'string');
		$this->addType('path', 'string');
		$this->addType('gid',  'string');
	}
}
