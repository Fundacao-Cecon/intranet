<?php
namespace App;

use App\Services\FileMeta;
use App\Services\FileStorage;
use App\Services\FolderAccess;
use App\Services\Groups;
use PDO;

/** Agrupa os serviços compartilhados que os controllers recebem. */
final class Context
{
    /** @var array */
    public $config;
    /** @var PDO */
    public $db;
    /** @var Auth */
    public $auth;
    /** @var Logger */
    public $logger;
    /** @var FileStorage */
    public $storage;
    /** @var FileMeta */
    public $meta;
    /** @var FolderAccess */
    public $access;
    /** @var Groups */
    public $groups;

    public function __construct(array $config, PDO $db, Auth $auth, Logger $logger, FileStorage $storage, FileMeta $meta, FolderAccess $access, Groups $groups)
    {
        $this->access = $access;
        $this->groups = $groups;
        $this->config = $config;
        $this->db = $db;
        $this->auth = $auth;
        $this->logger = $logger;
        $this->storage = $storage;
        $this->meta = $meta;
    }
}
