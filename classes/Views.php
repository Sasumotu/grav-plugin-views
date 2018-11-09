<?php
namespace Grav\Plugin\Views;

use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Config\Config;
use Grav\Plugin\Database\PDO;

class Views
{
    /** @var PDO */
    protected $db;

    protected $config;
    protected $path = 'user://data/views';
    protected $db_name = 'views.db';
    protected $table_total_views = 'total_views';

    public function __construct($config)
    {
        $this->config = new Config($config);
        $db_path = Grav::instance()['locator']->findResource($this->path, true, true);

        // Create dir if it doesn't exist
        if (!file_exists($db_path)) {
            Folder::create($db_path);
        }

        $connect_string = 'sqlite:' . $db_path . '/' . $this->db_name;

        $this->db = Grav::instance()['database']->connect($connect_string);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if (!$this->db->tableExists($this->table_total_views)) {
            $this->createTables();
        }
    }

    public function track($id, $amount = 1)
    {
        // Support SQLite < 3.24
        if (!$this->supportOnConflict()) {
            $query = "UPDATE {$this->table_total_views} SET count = count + :amount WHERE id = :id";

            $statement = $this->db->prepare($query);
            $statement->bindValue(':id', $id, PDO::PARAM_STR);
            $statement->bindValue(':amount', $amount, PDO::PARAM_INT);
            $statement->execute();

            if ($statement->rowCount() === 0) {
                $query = "INSERT INTO {$this->table_total_views} (id, count) VALUES (:id, :amount)";

                $statement = $this->db->prepare($query);
                $statement->bindValue(':id', $id, PDO::PARAM_STR);
                $statement->bindValue(':amount', $amount, PDO::PARAM_INT);
                $statement->execute();
            }

            return;
        }

        $query = "INSERT INTO {$this->table_total_views} (id, count) VALUES (:id, :amount) ON CONFLICT(id) DO UPDATE SET count = count + :amount";

        $statement = $this->db->prepare($query);
        $statement->bindValue(':id', $id, PDO::PARAM_STR);
        $statement->bindValue(':amount', $amount, PDO::PARAM_INT);
        $statement->execute();
    }

    public function set($id, $amount = 0)
    {
        // Support SQLite < 3.24
        if (!$this->supportOnConflict()) {
            $query = "UPDATE {$this->table_total_views} SET count = :amount WHERE id = :id";

            $statement = $this->db->prepare($query);
            $statement->bindValue(':id', $id, PDO::PARAM_STR);
            $statement->bindValue(':amount', $amount, PDO::PARAM_INT);
            $statement->execute();

            if ($statement->rowCount() === 0) {
                $query = "INSERT INTO {$this->table_total_views} (id, count) VALUES (:id, :amount)";

                $statement = $this->db->prepare($query);
                $statement->bindValue(':id', $id, PDO::PARAM_STR);
                $statement->bindValue(':amount', $amount, PDO::PARAM_INT);
                $statement->execute();
            }

            return;
        }

        $query = "INSERT INTO {$this->table_total_views} (id, count) VALUES (:id, :amount) ON CONFLICT(id) DO UPDATE SET count = :amount";

        $statement = $this->db->prepare($query);
        $statement->bindValue(':id', $id, PDO::PARAM_STR);
        $statement->bindValue(':amount', $amount, PDO::PARAM_INT);
        $statement->execute();
    }

    public function get($id)
    {
        $query = "SELECT count FROM {$this->table_total_views} WHERE id = :id";

        $statement = $this->db->prepare($query);
        $statement->bindValue(':id', $id, PDO::PARAM_STR);
        $statement->execute();

        $results = $statement->fetch();

        return $results['count'] ?? 0;
    }

    public function getAll($limit = 0, $order = 'ASC')
    {
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $offset = 0;

        $query = "SELECT count FROM {$this->table_total_views} ORDER BY count {$order} LIMIT :limit OFFSET :offset";

        $statement = $this->db->prepare($query);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function createTables()
    {
        $commands = [
            "CREATE TABLE IF NOT EXISTS {$this->table_total_views} (id VARCHAR(255) PRIMARY KEY, count INTEGER DEFAULT 0)",
        ];

        // execute the sql commands to create new tables
        foreach ($commands as $command) {
            $this->db->exec($command);
        }
    }

    protected function supportOnConflict()
    {
        static $bool;

        if ($bool === null) {
            $bool = version_compare($this->db->query('SELECT sqlite_version()')->fetch()[0], '3.24', '>=');
        }

        return $bool;
    }
}
