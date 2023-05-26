<?php
/**
 * user: peter
 * datetime：2023-05-24 15:43:29
 */

namespace Gdpeter\Tp6Addons\command;

use myttyy\Directory;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

/**
 * 版本创建命令，使用git diff命令进行增量迭代
 * 目前就支持根目录对比，因为暂时不需要插件
 */
class BuildVersion extends Command
{
    public function configure()
    {
        $this->setName('package:build')
            ->addArgument('diff_version', Argument::REQUIRED, '老版本v2.0.0')
            ->addArgument('build_version', Argument::REQUIRED, '新版本v2.0.1')
            ->setDescription('build version by git diff');
    }

    public function execute(Input $input, Output $output)
    {
        $diff_version = $input->getArgument('diff_version');
        $build_version = $input->getArgument('build_version');
        $build_path = root_path() . "package_build/";
        $build_file = $build_path . "{$build_version}.zip";
        Directory::create($build_path);

        $this->gitExec("git config --global core.quotepath false"); //git对比文件名称中文乱码
        $this->gitExec("git config --global diff.renamelimit 999999"); //git对比文件数量限制
        $this->gitExec("git config --global core.autocrlf false"); //Git中采取哪种对待换行符的方式,文本文件保持其原来的样子

        $this->gitExec("git remote update origin --prune"); //更新远程分支信息
        $this->gitExec("git fetch origin {$diff_version}"); //更新远程分支信息
        $this->gitExec("git fetch origin {$build_version}"); //更新远程分支信息
        $this->gitExec("git archive origin/{$build_version} -o {$build_file} $(git diff origin/{$diff_version} origin/{$build_version} --name-only --diff-filter=ACMR)");
        $output->writeln('create package success');
    }

    //执行命令
    private function gitExec($cmd)
    {
        echo "\n".$cmd."\n";
        exec($cmd, $output, $return);
        return $output;
    }
}