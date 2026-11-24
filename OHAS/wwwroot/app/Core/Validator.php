<?php
/**
 * 验证器：声明式规则，产出中文错误信息。
 * 规则：required, date, in:.., max:n, int, numeric, nonneg, idcard, phone, digits:n
 */
declare(strict_types=1);

namespace App\Core;

class Validator
{
    private array $data;
    private array $rules;
    private array $labels;
    private array $errors = [];

    public function __construct(array $data, array $rules, array $labels = [])
    {
        $this->data = array_map(fn ($v) => is_string($v) ? trim((string)$v) : $v, $data);
        $this->rules = $rules;
        $this->labels = $labels;
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function fails(): bool
    {
        $this->validate();
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /** 验证后的干净数据（仅含规则中出现的键） */
    public function validated(): array
    {
        $out = [];
        foreach ($this->rules as $key => $ruleStr) {
            $v = $this->data[$key] ?? '';
            if (is_array($v)) {
                $v = '';
            }
            // 日期统一补零为 YYYY-MM-DD：系统多处按字符串比较/排序日期，"2026-1-5" 会排到 "2026-01-10" 之后
            if (is_string($v) && $v !== '' && in_array('date', explode('|', (string)$ruleStr), true)) {
                $v = self::normalizeDate($v);
            }
            $out[$key] = $v;
        }
        return $out;
    }

    private function validate(): void
    {
        foreach ($this->rules as $key => $ruleStr) {
            $value = $this->data[$key] ?? null;
            $label = $this->labels[$key] ?? $key;
            if (is_array($value)) {
                $this->err($key, "$label 格式不正确。");
                continue;
            }
            foreach (explode('|', $ruleStr) as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $isEmpty = $value === null || (is_string($value) && $value === '');
                switch ($name) {
                    case 'required':
                        if ($isEmpty) {
                            $this->err($key, "$label 不能为空。");
                        }
                        break;
                    case 'date':
                        if (!$isEmpty && !self::isDate((string)$value)) {
                            $this->err($key, "$label 应为合法日期（YYYY-MM-DD）。");
                        }
                        break;
                    case 'in':
                        if (!$isEmpty && !in_array((string)$value, explode(',', (string)$arg), true)) {
                            $this->err($key, "$label 的取值不正确。");
                        }
                        break;
                    case 'max':
                        if (!$isEmpty && mb_strlen((string)$value) > (int)$arg) {
                            $this->err($key, "$label 长度不能超过 $arg 字。");
                        }
                        break;
                    case 'int':
                        if (!$isEmpty && !preg_match('/^\d{1,9}$/', (string)$value)) {
                            $this->err($key, "$label 应为非负整数（最多 9 位）。");
                        }
                        break;
                    case 'not_before':   // 日期不得早于另一字段（对方为空则跳过），如 结束日期 ≥ 开始日期
                        $other = (string)($this->data[$arg] ?? '');
                        if (!$isEmpty && $other !== '' && self::normalizeDate((string)$value) < self::normalizeDate($other)) {
                            $this->err($key, "$label 不能早于" . ($this->labels[$arg] ?? $arg) . '。');
                        }
                        break;
                    case 'not_future':   // 日期不得晚于今天（检查/签字/离岗等事实日期，不接受未来日期）
                        if (!$isEmpty && self::normalizeDate((string)$value) > date('Y-m-d')) {
                            $this->err($key, "$label 不能晚于今天。");
                        }
                        break;
                    case 'numeric':
                        if (!$isEmpty && !preg_match('/^-?\d+(\.\d+)?$/', (string)$value)) {
                            $this->err($key, "$label 应为数字。");
                        }
                        break;
                    case 'nonneg':   // 非负数字（可带小数），用于人数 / 学时等不允许负值的字段
                        if (!$isEmpty && !preg_match('/^\d+(\.\d+)?$/', (string)$value)) {
                            $this->err($key, "$label 应为大于等于 0 的数字。");
                        }
                        break;
                    case 'idcard':
                        if (!$isEmpty && !\App\Helpers\IdCard::valid((string)$value)) {
                            $this->err($key, "$label 格式不正确（应为合法的18位身份证号）。");
                        }
                        break;
                    case 'phone':
                        if (!$isEmpty && !preg_match('/^1[3-9]\d{9}$|^\d{3,4}-?\d{7,8}$/', (string)$value)) {
                            $this->err($key, "$label 应为 11 位手机号或座机号。");
                        }
                        break;
                }
            }
        }
    }

    /** 合法日期补零为 YYYY-MM-DD；不合法原样返回 */
    public static function normalizeDate(string $s): string
    {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
        }
        return $s;
    }

    public static function isDate(string $s): bool
    {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) {
            return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
        }
        return false;
    }

    private function err(string $key, string $msg): void
    {
        $this->errors[$key] = $msg;
    }
}
