<?php
namespace Leap\Plugins\PluginManager\Controllers;

use \Leap\Plugins\Admin\Controllers\AdminController;
use \Composer\Console\Application;
use \Symfony\Component\Console\Input\ArrayInput;
use \Symfony\Component\Console\Output\StreamOutput;

    /*public static function dump() {
        $composer_file = ROOT . 'composer.json';
        if (file_exists($composer_file)) {
            $composer_json = json_decode(file_get_contents($composer_file), true);
            return $composer_json['require'];
        } else {
            return array();
        }
    }*/

    /*protected static function createComposerJson($packages) {
        $composer_json = str_replace("\/", '/', json_encode(array(
            'config' => array('vendor-dir' => "libraries"),
            'require' => $packages,
            //
            // TODO:
            // windowsazure requires PEAR repository
            //
            'repositories' => array(array(
                'type' => 'pear',
                'url' => 'http://pear.php.net'
            )),
            'preferred-install' => 'dist'
        )));
        return file_put_contents(storage_dir() . 'composer.json', $composer_json);
    }*/


class pluginController extends AdminController
{
    public function getPlugins()
    {
        $plugins = array();
        if ($this->model->hasConnection()) {
            $stmt    = $this->model->query("SELECT * FROM plugins");
            $plugins = $stmt->fetchAll();
        } else {
            foreach ($this->plugin_manager->all_plugins as $plugin => $path) {
                $plugin_info           = $this->plugin_manager->all_plugins[$plugin];
                $enabled               = $this->plugin_manager->isEnabled($plugin);
                $plugin_info['pid']    = $plugin;
                $plugin_info['status'] = $enabled;
                if (!empty($plugin_info['dependencies'])) {
                    $plugin_info['dependencies'] = implode(",", $plugin_info['dependencies']);
                }
                $plugins[] = $plugin_info;
            }
        }
        $this->set('plugins', $plugins);
        //$this->composerRequire("twig/twig");
        //$this->composerUpdate();
    }

    public function composerRequire($package) {
        ini_set('memory_limit', '512M');
        // Don't proceed if packages haven't changed.
        //if ($packages == $this->dump()) { return false; }
        putenv('COMPOSER_HOME=' . ROOT . 'libraries/composer/composer');
        //$this->createComposerJson($packages);
        chdir(ROOT);
        // Setup composer output formatter
        $stream = fopen('php://temp', 'w+');
        $output = new StreamOutput($stream);
        // Programmatically run `composer install`
        $application = new Application();
        $application->setAutoExit(false);
        $code = $application->run(new ArrayInput(array('command' => 'require', 'packages' => array($package))), $output);
        // remove composer.lock
        // if (file_exists(ROOT . 'composer.lock')) {
        //     unlink(ROOT . 'composer.lock');
        // }
        // rewind stream to read full contents
        rewind($stream);
        $this->set('output',stream_get_contents($stream));
    }
    public function composerUpdate() {
        ini_set('memory_limit', '512M');
        // Don't proceed if packages haven't changed.
        //if ($packages == $this->dump()) { return false; }
        putenv('COMPOSER_HOME=' . ROOT . 'libraries/composer/composer');
        //$this->createComposerJson($packages);
        chdir(ROOT);
        // Setup composer output formatter
        $stream = fopen('php://temp', 'w+');
        $output = new StreamOutput($stream);
        // Programmatically run `composer update`
        $application = new Application();
        $application->setAutoExit(false);
        $code = $application->run(new ArrayInput(array('command' => 'update')), $output);
        // remove composer.lock
        // if (file_exists(ROOT . 'composer.lock')) {
        //     unlink(ROOT . 'composer.lock');
        // }
        // rewind stream to read full contents
        rewind($stream);
        $this->set('output',stream_get_contents($stream));
    }

    public function enablePlugin($plugin = null, $checkDependencies = true)
    {
        if (empty($plugin)) {
            $plugin = $_POST['pid'];
        }
        if ($plugin) {
            if ($checkDependencies) {
                $dependencies = $this->getDependencies($plugin);
                /* check if there is atleast 1 dependency (not counting yourself) */
                if (isset($dependencies[1])) {
                    $this->set('dependencies', $dependencies);
                    return;
                }
            }
            if (isset($this->plugin_manager->all_plugins[$plugin])) {
                if ($this->model->hasConnection()) {
                    $sql = "UPDATE plugins SET status=1 WHERE pid= ? ";
                    // Perform Query
                    $stmt = $this->model->run($sql, [$plugin]);
                    if ($stmt->rowCount()) {
                        $message = "Plugin <b>" . $this->plugin_manager->all_plugins[$plugin]['name'] . "</b> successfully enabled.";
                    } else {
                        $error = "Could not enable plugin <b>" . $this->plugin_manager->all_plugins[$plugin]['name'] . "</b>.<br>";
                    }
                } else {
                    $path = $this->plugin_manager->all_plugins[$plugin]['path'];
                    if (rename($path . $plugin . ".disabled", $path . $plugin . ".info")) {
                        $message = "Plugin <b>" . $this->plugin_manager->all_plugins[$plugin]['name'] . "</b> successfully enabled.";
                    } else {
                        $error = "Could not enable plugin <b>" . $this->plugin_manager->all_plugins[$plugin]['name'] . "</b>.<br>";
                        $info  = "As you have no database connection, you can also try to enable plugin manually by changing the [plugin].disabled file to [plugin].info";
                    }
                }
            } else {
                $error = "Plugin <b>" . $this->plugin_manager->all_plugins[$plugin]['name'] . "</b> not found.";
            }
        } else {
            $error = "No plugin specified";
        }

        if (isset($message)) {
            set_message($message, "success");
        }
        if (isset($error)) {
            set_message($error, "error");
        }
        if (isset($info)) {
            set_message($info, "info");
        }
        if ($checkDependencies) {
            header("Location: " . BASE_URL . "admin/plugins");
        }
    }

    public function disablePlugin($plugin = null, $checkDependents = true)
    {
        if (empty($plugin)) {
            $plugin = $_POST['pid'];
        }
        if ($plugin) {
            if ($checkDependents) {
                $dependents = $this->getDependents($plugin);
                /* check if there is atleast 1 dependent plugin (not counting yourself) */
                if (isset($dependents[1])) {
                    $this->set('dependent_plugins', $dependents);
                    return;
                }
            }
            if ($this->model->hasConnection()) {
                $sql = "UPDATE plugins SET status=0 WHERE pid= ? ";
                // Perform Query
                $stmt = $this->model->run($sql, [$plugin]);
                if ($stmt->rowCount()) {
                    $message = "Plugin <b>" . $this->plugin_manager->all_plugins[$plugin]['name'] . "</b> successfully disabled.";
                } else {
                    $error = "Could not disable plugin <b>" . $this->plugin_manager->all_plugins[$plugin]['name'] . "</b>.<br>";
                }

            } else {
                if (isset($this->plugin_manager->all_plugins[$plugin])) {
                    $path = $this->plugin_manager->all_plugins[$plugin]['path'];
                    if (rename($path . $plugin . ".info", $path . $plugin . ".disabled")) {
                        $message = "Plugin <b>" . $this->plugin_manager->all_plugins[$plugin]['name'] . "</b> successfully disabled.";
                    } else {
                        $error = "No database connection and plugin folder isn't writable, please disable plugin manually by changing the .info file to .disabled";
                    }
                } else {
                    $error = "Plugin <b>" . $this->plugin_manager->all_plugins[$plugin]['name'] . "</b> not found.";
                }
            }
        } else {
            $error = "No plugin specified";
        }

        if (isset($message)) {
            set_message($message, "success");
        }
        if (isset($error)) {
            set_message($error, "error");
        }
        if (isset($info)) {
            set_message($info, "info");
        }
        if ($checkDependents) {
            header("Location: " . BASE_URL . "admin/plugins");
        }
    }

    public function multiplePlugins()
    {
        if (isset($_POST['action']) && $_POST['action'] == "Disable") {
            $plugins = unserialize($_POST['plugins']);
            foreach ($plugins as $plugin) {
                $this->disablePlugin($plugin, false);
            }
        } else if (isset($_POST['action']) && $_POST['action'] == "Enable") {
            $plugins = unserialize($_POST['plugins']);
            foreach ($plugins as $plugin) {
                $this->enablePlugin($plugin, false);
            }
        }
        header("Location: " . BASE_URL . "admin/plugins");
    }

    /* recursive dependent plugins checker */
    private function getDependents($plugin, $current_dependencies = null)
    {
        $dependent_plugins = [];
        if (!isset($current_dependencies)) {
            $dependent_plugins[] = $plugin;
        }

        foreach ($this->plugin_manager->enabled_plugins as $enabled_plugin) {
            if (!empty($this->plugin_manager->all_plugins[$enabled_plugin]['dependencies'])) {
                if (in_array($plugin, $this->plugin_manager->all_plugins[$enabled_plugin]['dependencies'])) {
                    if (!isset($current_dependencies) || !in_array($enabled_plugin, $current_dependencies)) {
                        $dependent_plugins[] = $enabled_plugin;
                        $dependent_plugins   = $this->getDependents($enabled_plugin, $dependent_plugins);
                    }
                }
            }
        }
        if (isset($current_dependencies)) {
            return array_merge($current_dependencies, $dependent_plugins);
        } else {
            return array_unique($dependent_plugins);
        }
    }

    /* recursive dependencies checker */
    private function getDependencies($plugin, $current_dependencies = null)
    {
        $dependent_plugins = [];
        if (!isset($current_dependencies)) {
            $dependent_plugins[] = $plugin;
        }

        if (!empty($this->plugin_manager->all_plugins[$plugin]['dependencies'])) {
            foreach ($this->plugin_manager->all_plugins[$plugin]['dependencies'] as $dependency) {
                if (!in_array($dependency, $this->plugin_manager->enabled_plugins)) {
                    if (!isset($current_dependencies) || !in_array($dependency, $current_dependencies)) {
                        $dependent_plugins[] = $dependency;
                        $dependent_plugins   = $this->getDependents($dependency, $dependent_plugins);
                    }
                }
            }
        }
        if (isset($current_dependencies)) {
            return array_merge($current_dependencies, $dependent_plugins);
        } else {
            return array_unique($dependent_plugins);
        }
    }
}
