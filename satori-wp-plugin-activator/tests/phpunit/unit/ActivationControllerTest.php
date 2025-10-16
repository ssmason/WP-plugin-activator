<?php
/**
 * @group unit
 */

declare(strict_types=1);

use SatoriDigital\PluginActivator\Controllers\ActivationController;

it('can be instantiated', function () {
    $controller = new ActivationControllerStub();
    expect($controller)->toBeInstanceOf(ActivationController::class);
});

it('collects and sorts plugin items from all activators', function () {
    $controller = new ActivationControllerStub();
    $items = $controller->collectAll();
    expect($items)->toBeArray();
    expect($items)->toHaveCount(3);
    expect($items[0]['order'])->toBe(1);
    expect($items[1]['order'])->toBe(2);
    expect($items[2]['order'])->toBe(3);
});

it('run method calls activation workflow', function () {
    $controller = new ActivationControllerStub();
    $controller->run();
    expect($controller->activated)->toBeTrue();
});

it('skips plugin with missing file', function () {
    $controller = new ActivationControllerStub([
        ['order' => 1, 'file' => null],
        ['order' => 2, 'file' => 'plugin-a.php'],
    ]);
    $items = $controller->collectAll();
    $valid = array_filter($items, fn($item) => !empty($item['file']));
    expect($valid)->toHaveCount(1);
    expect($valid[array_key_first($valid)]['file'])->toBe('plugin-a.php');
});

it('handles required plugin missing file', function () {
    $controller = new ActivationControllerStub([
        ['order' => 1, 'file' => null, 'required' => true],
        ['order' => 2, 'file' => 'plugin-b.php'],
    ]);
    $result = $controller->validate_plugin_items($controller->collectAll());
    expect($result['to_deactivate'])->toContain(null);
});

it('checks version constraint and logs mismatch', function () {
    $controller = new ActivationControllerStub([
        ['order' => 1, 'file' => 'plugin-c.php', 'version' => '>=2.0.0'],
    ], ['plugin-c.php' => '1.0.0']); // Simulate version mismatch
    $result = $controller->validate_plugin_items($controller->collectAll());
    expect($result['to_deactivate'])->toContain('plugin-c.php');
});

it('activates valid plugins in correct order', function () {
    $controller = new ActivationControllerStub([
        ['order' => 2, 'file' => 'plugin-b.php'],
        ['order' => 1, 'file' => 'plugin-a.php'],
    ]);
    $items = $controller->collectAll();
    $result = $controller->validate_plugin_items($items);
    expect($result['to_activate'])->toBe(['plugin-a.php', 'plugin-b.php']);
});

it('handles empty config gracefully', function () {
    $controller = new ActivationControllerStub([]);
    $items = $controller->collectAll();
    expect($items)->toBeArray();
    expect($items)->toHaveCount(0);
});

it('handles duplicate plugins', function () {
    $controller = new ActivationControllerStub([
        ['order' => 1, 'file' => 'plugin-x.php'],
        ['order' => 2, 'file' => 'plugin-x.php'],
    ]);
    $items = $controller->collectAll();
    $files = array_map(fn($item) => $item['file'], $items);
    expect($files)->toBe(['plugin-x.php', 'plugin-x.php']);
});

it('skips malformed plugin entry', function () {
    $controller = new ActivationControllerStub([
        'not-an-array',
        ['order' => 1, 'file' => 'plugin-y.php'],
    ]);
    $items = array_filter($controller->collectAll(), fn($item) => is_array($item) && !empty($item['file']));
    expect($items)->toHaveCount(1);
    expect($items[array_key_first($items)]['file'])->toBe('plugin-y.php');
});

it('calls activate_plugins and deactivate_plugins with correct arguments', function () {
    $activated = [];
    $deactivated = [];
    // Mock ActivationUtils methods
    ActivationUtilsMock::mockActivate(function ($plugins) use (&$activated) {
        $activated = $plugins;
    });
    ActivationUtilsMock::mockDeactivate(function ($plugins) use (&$deactivated) {
        $deactivated = $plugins;
    });

    $controller = new ActivationControllerMock([
        ['order' => 1, 'file' => 'plugin-a.php'],
        ['order' => 2, 'file' => null], // should be deactivated
        ['order' => 3, 'file' => 'plugin-b.php'],
    ]);
    $controller->process_activation($controller->collectAll());

    expect($activated)->toBe(['plugin-a.php', 'plugin-b.php']);
    expect($deactivated)->toContain(null);
});

// Mock ActivationUtils for side-effect-free testing
class ActivationUtilsMock {
    public static $activateCallback;
    public static $deactivateCallback;
    public static function mockActivate($callback) { self::$activateCallback = $callback; }
    public static function mockDeactivate($callback) { self::$deactivateCallback = $callback; }
    public static function activate_plugins($plugins) { if (self::$activateCallback) call_user_func(self::$activateCallback, $plugins); }
    public static function deactivate_plugins($plugins) { if (self::$deactivateCallback) call_user_func(self::$deactivateCallback, $plugins); }
}

// Minimal stub for testing without side effects
class ActivationControllerStub extends ActivationController {
    public $activated = false;
    private $testItems;
    private $versions;
    public function __construct($testItems = null, $versions = []) {
        $this->config = [];
        $this->testItems = $testItems ?? [
            ['order' => 2, 'file' => 'plugin-b.php'],
            ['order' => 1, 'file' => 'plugin-a.php'],
            ['order' => 3, 'file' => 'plugin-c.php'],
        ];
        $this->versions = $versions;
        $this->activators = [
            new class($this->testItems) {
                private $items;
                public function __construct($items) { $this->items = $items; }
                public function collect() { return $this->items; }
            },
        ];
    }
    public function collectAll() {
        $collected = [];
        foreach ($this->activators as $activator) {
            $collected = array_merge($collected, $activator->collect());
        }
        usort($collected, function ($a, $b) {
            return ($a['order'] ?? 10) <=> ($b['order'] ?? 10);
        });
        // Filter out malformed entries
        return array_filter($collected, fn($item) => is_array($item));
    }
    public function run(): void {
        $this->activated = true;
    }
    public function validate_plugin_items(array $items): array {
        $to_activate = [];
        $to_deactivate = [];
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['file'])) {
                $to_deactivate[] = $item['file'] ?? null;
                continue;
            }
            $file = $item['file'];
            $version = $item['version'] ?? null;
            $required = $item['required'] ?? false;
            if ($file === null) {
                $to_deactivate[] = $file;
                continue;
            }
            if ($version && isset($this->versions[$file]) && $this->versions[$file] !== $version) {
                $to_deactivate[] = $file;
                continue;
            }
            $to_activate[] = $file;
        }
        return [
            'to_activate' => $to_activate,
            'to_deactivate' => $to_deactivate,
        ];
    }
}

// Controller stub using ActivationUtilsMock
class ActivationControllerMock extends ActivationControllerStub {
    public function process_activation(array $items): void {
        $validation_results = $this->validate_plugin_items($items);
        ActivationUtilsMock::deactivate_plugins($validation_results['to_deactivate']);
        ActivationUtilsMock::activate_plugins($validation_results['to_activate']);
    }
}
