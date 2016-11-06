<?php

/**
 * File helper.
 *
 * @package    Mamuph File Helper
 * @category   Helpers
 * @author     Mamuph Team
 * @copyright  (c) 2016 Mamuph Team
 */
class Core_File
{

    // Scope levels
    const SCOPE_AUTO     = 1;
    const SCOPE_LOCAL    = 2;
    const SCOPE_EXTERNAL = 3;


    // Flags (As bitmask)
    const EXCLUDE_NONE           =   0;
    const EXCLUDE_BLOCK          =   1;
    const EXCLUDE_FILES          =   2;
    const EXCLUDE_DIRECTORIES    =   4;
    const EXCLUDE_LINKS          =   8;
    const EXCLUDE_EXECUTABLE     =  16;
    const EXCLUDE_READABLE       =  32;
    const EXCLUDE_WRITABLE       =  64;
    const EXCLUDE_HIDDEN         = 128;
    const LIST_RECURSIVE         = 256;



    /**
     * Get a list of files from a specified directory
     *
     * @param string     $path              Path
     * @param bool       $get_fullpath      Return full path (Default: false)
     * @param int        $flag              Exclusion type (Default: File::EXCLUDE_BLOCK)
     * @param int        $sorting_order     Sorting order (Default: SCANDIR_SORT_ASCENDING)
     * @return array|bool
     *
     * @link http://php.net/manual/en/function.scandir.php
     */
    public static function ls($path, $get_fullpath = false, $flag = File::EXCLUDE_BLOCK, $sorting_order = SCANDIR_SORT_ASCENDING)
    {

        $files = scandir($path, $sorting_order);


        // No files, then there is nothing to do
        if (empty($files) || count($files) === 0)
            return false;

        $filtered_files = [];

        foreach ($files as $file)
        {

            $fullpath = $path . DS . $file;

            // Exclude block files
            if (($flag & File::EXCLUDE_BLOCK) && ($file === '.' || $file === '..'))
                continue;

            // Exclude generic files
            if (($flag & File::EXCLUDE_FILES) && is_file($fullpath))
                continue;

            // Exclude links
            if (($flag & File::EXCLUDE_LINKS) && is_link($fullpath))
                continue;

            // Exclude executables
            if (($flag & File::EXCLUDE_EXECUTABLE) && is_executable($fullpath))
                continue;

            // Exclude readable
            if (($flag & File::EXCLUDE_READABLE) && is_readable($fullpath))
                continue;

            // Exclude writable
            if (($flag & File::EXCLUDE_WRITABLE) && is_writable($fullpath))
                continue;

            // Exclude hidden
            if (($flag & File::EXCLUDE_HIDDEN) && basename($file)[0] === '.')
                continue;

            // Exclude directories
            if (!(($flag & File::EXCLUDE_DIRECTORIES) && is_dir($fullpath)))
                $filtered_files[] = $get_fullpath ? $fullpath : $file;

            // Recursive list
            if (($flag & File::LIST_RECURSIVE) && is_dir($fullpath))
                $filtered_files = array_merge($filtered_files, static::ls($fullpath, $get_fullpath, $flag, $sorting_order));

        }

        return $filtered_files;

    }



    /**
     * Check if file exists
     *
     * @example
     *
     *      // Check if file exists inside project or PHAR
     *      File::exists($filename, File::SCOPE_LOCAL);
     *
     *      // Check if file exists outside the project or PHAR
     *      File::exists($filename, File::SCOPE_EXTERNAL);
     *
     * @param string    $path   File path
     * @param int       $scope  Search scope (Default: FILESCOPE_AUTO)
     * @return bool
     */
    public static function exists($path, $scope = File::SCOPE_AUTO)
    {

        if ($scope === File::SCOPE_EXTERNAL && IS_PHAR)
        {
            if ($path[0] != DS)
                return file_exists(getcwd() . DS . $path);
        }

        if (file_exists($path))
            return true;

        if ($scope === File::SCOPE_AUTO)
        {

            if (IS_PHAR)
            {
                if (file_exists(dirname(Phar::running(false)) . DS . $path))
                    return true;

                if (file_exists(getcwd() . DS . $path))
                    return true;
            }

            return false;
        }

        return false;

    }



    /**
     * Delete a directory and their included content.
     * Take care when you use this method :)
     *
     * @param   string  $directory  Directory path
     * @param   bool    $recursive  Delete sub-directories (Default: true)
     * @param   int     $flag
     * @return  bool
     * @throws Exception
     */
    public static function deltree($directory, $recursive = true, $flag = File::EXCLUDE_BLOCK)
    {

        if (realpath($directory) == '/')
            throw new Exception("Operation not allowed");

        // Block files cannot be copied
        $flag = $flag | File::EXCLUDE_BLOCK;

        // Directories can be removed, however are listed.
        $lsflag = $flag >= File::EXCLUDE_DIRECTORIES ? $flag - File::EXCLUDE_DIRECTORIES : $flag;

        $files = File::ls($directory, true, $lsflag);

        foreach ($files as $file)
        {

            $status = false;

            if ($recursive && is_dir($file))
                $status = File::deltree($file, $recursive, $flag);
            else
                $status = unlink($file);

            if (!$status)
                return false;

        }

        // Remove self container directory
        if (!($flag & File::EXCLUDE_FILES))
        {
            if (!rmdir($directory))
                return false;
        }

        return true;

    }



    /**
     * Copy a file, or recursively copy a folder and its contents
     *
     * @param       string   $source    Source path
     * @param       string   $dest      Destination path
     * @param       int      $permissions New folder creation permissions
     * @param       int      $flag
     * @return      bool     Returns true on success, false on failure
     */
    public static function xcopy($source, $dest, $permissions = 0755, $flag = File::EXCLUDE_NONE)
    {

        // Block files cannot be copied
        $flag = $flag | File::EXCLUDE_BLOCK;


        // Copy all the files
        if (basename($source) === '*')
        {
            $files = File::ls(dirname($source), true, $flag);

            foreach ($files as $file)
            {
                if (!File::xcopy($file, $dest, $permissions, $flag))
                    return false;
            }

            return true;
        }


        // Copy symlink
        if (is_link($source))
            return ($flag & File::EXCLUDE_LINKS) ? false : symlink(readlink($source), $dest);


        // Copy regular file
        if (is_file($source))
        {
            // Exclude executables
            if (($flag & File::EXCLUDE_EXECUTABLE) && is_executable($source))
                return false;

            // Exclude readable
            if (($flag & File::EXCLUDE_READABLE) && is_readable($source))
                return false;

            // Exclude writable
            if (($flag & File::EXCLUDE_WRITABLE) && is_writable($source))
                return false;

            // Exclude hidden
            if (($flag & File::EXCLUDE_HIDDEN) && basename($source)[0] === '.')
                return false;

            return copy($source, $dest . DS . basename($source));
        }


        // Make directory
        if (is_dir($source))
        {
            // Exclude directories
            if (($flag & File::EXCLUDE_DIRECTORIES))
                return false;

            $dest = $dest . DS . basename($source);

            if (!mkdir($dest, $permissions))
                return false;
        }

        // Loop through the folder
        $files = File::ls($source, true, $flag);

        foreach ($files as $file)
            File::xcopy($file,  $dest, $permissions, $flag);

        return true;

    }
}