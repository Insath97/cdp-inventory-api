<?php
$files = glob(__DIR__ . '/app/Http/Requests/*Request.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, '\'slug\'') !== false || strpos($content, '"slug"') !== false) {
        if (strpos($content, 'prepareForValidation') === false) {
            $insert = "
    protected function prepareForValidation()
    {
        if (\$this->has('name')) {
            \$this->merge([
                'slug' => \Illuminate\Support\Str::slug(\$this->name),
            ]);
        }
    }
";
            $content = preg_replace('/public function rules\(\):\s*array\s*\{/', $insert . "\n" . '    public function rules(): array' . "\n" . '    {', $content);
            file_put_contents($file, $content);
            echo 'Updated ' . basename($file) . PHP_EOL;
        }
    }
}
echo "Done\n";
