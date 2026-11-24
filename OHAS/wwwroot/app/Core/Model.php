<?php
/**
 * 基础模型：单表读写封装，子类声明表名即可获得常用能力。
 */
declare(strict_types=1);

namespace App\Core;

abstract class Model
{
    protected static string $table = '';

    public static function table(): string
    {
        return static::$table;
    }

    public static function find(int $id): ?array
    {
        return Database::row('SELECT * FROM ' . static::$table . ' WHERE id = ?', [$id]);
    }

    public static function all(string $orderBy = 'id ASC'): array
    {
        return Database::rows('SELECT * FROM ' . static::$table . ' ORDER BY ' . $orderBy);
    }

    public static function insert(array $data): int
    {
        $data['created_at'] = now_str();
        $data['updated_at'] = now_str();
        $cols = array_keys($data);
        $sql = 'INSERT INTO ' . static::$table . ' (' . implode(',', $cols) . ') VALUES ('
            . implode(',', array_fill(0, count($cols), '?')) . ')';
        Database::run($sql, array_values($data));
        return Database::lastId();
    }

    public static function update(int $id, array $data): void
    {
        if (!$data) {
            return;
        }
        $data['updated_at'] = now_str();
        $sets = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($data)));
        Database::run('UPDATE ' . static::$table . ' SET ' . $sets . ' WHERE id = ?', array_merge(array_values($data), [$id]));
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM ' . static::$table . ' WHERE id = ?', [$id]);
    }

    public static function count(string $where = '1=1', array $params = []): int
    {
        return (int)Database::value('SELECT COUNT(*) FROM ' . static::$table . ' WHERE ' . $where, $params);
    }

    /** 分页查询：返回 ['rows'=>, 'total'=>, 'page'=>, 'pages'=>] */
    public static function paginate(string $where, array $params, string $orderBy, int $page, int $size): array
    {
        $total = static::count($where, $params);
        $pages = max(1, (int)ceil($total / $size));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $size;
        $rows = Database::rows(
            'SELECT * FROM ' . static::$table . ' WHERE ' . $where . ' ORDER BY ' . $orderBy
                . ' LIMIT ' . $size . ' OFFSET ' . $offset,
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }
}
