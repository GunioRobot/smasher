<?php

class Smasher
{
    protected $config_xml;
    protected $temp_dir = '/tmp';
    protected $root_dir = NULL;
    protected $java_bin = 'java';
    protected $yuicompressor = 'yuicompressor.jar';

    protected function load_config($config_file)
    {
        $this->config_xml = @simplexml_load_file($config_file);
        if (!$this->config_xml) {
            throw new Exception('Cannot load config file: ' . $config_file);
        }

        if ($this->config_xml->temp_dir) {
            $this->temp_dir = (string) $this->config_xml->temp_dir;
        }

        if ($this->config_xml->root_dir) {
            $this->root_dir = (string) $this->config_xml->root_dir;
        }

        if ($this->config_xml->java_bin) {
            $this->java_bin = (string) $this->config_xml->java_bin;
        }

        if ($this->config_xml->yuicompressor) {
            $this->yuicompressor = (string) $this->config_xml->yuicompressor;
        }
    }

    protected function get_group_xml($id)
    {
        $group_xml = $this->config_xml->xpath("group[@id='$id']");
        return count($group_xml) === 1 ? $group_xml[0] : NULL;
    }

    protected function concatenate($files)
    {
        $temp_name = tempnam($this->temp_dir, '.smasher-');
        $temp_file = fopen($temp_name, 'w+');

        foreach ($files as $file) {
            fwrite($temp_file, file_get_contents($file));
            fwrite($temp_file, "\n");
        }

        fclose($temp_file);
        return $temp_name;
    }

    protected function preprocess($file, $macros)
    {
        $temp_name = tempnam($this->temp_dir, '.smasher-cpp-');

        $cpp_args = array();
        foreach ($macros as $name => $value) {
            $cpp_args[] = '-D ' .
                escapeshellarg($name) . '=' . escapeshellarg($value);
        }

        shell_exec('cpp -P -C 2>/dev/null ' .
            implode(' ', $cpp_args) . ' ' .
            escapeshellarg($file) . ' > ' .
            escapeshellarg($temp_name));

        return $temp_name;
    }

    protected function minify($file, $type)
    {
        return shell_exec($this->java_bin .
            ' -jar ' . $this->yuicompressor .
            ' --type ' . $type .
            ' ' . escapeshellarg($file));
    }

    public function build_js($group, $minify = true)
    {
        $group_xml = $this->get_group_xml($group);
        if (!$group_xml) {
            throw new Exception('Invalid group: ' . $group);
        }

        $files = array();
        foreach ($group_xml->xpath("file[@type='js']") as $file) {
            $files []= $this->root_dir . ((string) $file['src']);
        }

        $macros = array();
        foreach ($group_xml->xpath("macro") as $macro) {
            $name  = (string) $macro['name'];
            $value = (string) $macro['value'];
            $macros[$name] = $value;
        }

        $concatenated_file = $this->concatenate($files);
        $preprocessed_file = $this->preprocess($concatenated_file, $macros);

        if ($minify) {
            $js = $this->minify($preprocessed_file, 'js');
        } else {
            $js = file_get_contents($preprocessed_file);
        }

        unlink($concatenated_file);
        unlink($preprocessed_file);
        return $js;
    }

    public function build_css($group, $minify = true)
    {
        $group_xml = $this->get_group_xml($group);
        if (!$group_xml) {
            throw new Exception('Invalid group: ' . $group);
        }

        $files = array();
        foreach ($group_xml->xpath("file[@type='css']") as $file) {
            $files []= $this->root_dir . ((string) $file['src']);
        }

        $concatenated_file = $this->concatenate($files);

        if ($minify) {
            $css = $this->minify($concatenated_file, 'css');
        } else {
            $css = file_get_contents($concatenated_file);
        }

        unlink($concatenated_file);
        return $css;
    }

    public function __construct($config_file)
    {
        $this->load_config($config_file);
    }
}
