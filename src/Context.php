<?php
namespace App;

use App\Services\FileMeta;
use App\Services\FileStorage;
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

    public function __construct(array $config, PDO $db, Auth $auth, Logger $logger, FileStorage $storage, FileMeta $meta)
    {
        $this->config = $config;
        $this->db = $db;
        $this->auth = $auth;
        $this->logger = $logger;
        $this->storage = $storage;
        $this->meta = $meta;
    }
}
