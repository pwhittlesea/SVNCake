<?php
/**
*
* SVN model for the SVNCake plugin
* Performs the hard graft of fetching SVN data
*
* Licensed under The MIT License
* Redistributions of files must retain the above copyright notice.
*
* @copyright     SVNCake Development Team 2012
* @link          http://github.com/pwhittlesea/svncake
* @package       SVNCake.Model
* @since         SVNCake v 0.1
* @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
*/

class SVNCake extends SVNCakeAppModel {

    // Reference to our copy of the open svn repo
    public $repo = null;

    // We dont need no table
    public $useTable = null;

    /*
     * loadRepo
     * Load the repo at a location
     *
     * @param $base string the path to load
     * @return boolean true if repo is loaded
     */
    public function loadRepo($base = null) {
        $this->repo = "file://$base";
    }

    /*
     * createRepo
     * Create a repo at a location
     *
     * @param $base string the path to use
     * @return boolean true if repo is created
     */
    public function createRepo($base = null) {
    }

    /*
     * repoLoaded
     * Check that a repo has been loaded
     *
     * @return boolean true if repo is loaded
     */
    public function repoLoaded() {
        return ($this->repo) ? true : false;
    }

    /*
     * branch
     * Fetch repos branches
     *
     * @return array list of branches
     */
    public function branch() {
        if (!$this->repoLoaded()) return null;
    }

    /*
     * hasTree
     * Check if repo has a tree
     *
     * @param $hash string the tree to look up
     * @return boolean true if tree exists
     */
    public function hasTree($hash) {
        if (!$this->repoLoaded()) return null;
    }

    /*
     * tree
     * Return the contents of a tree
     *
     * @param $branch string the node to look up
     * @param $path string the path to examine
     * @param $rev string revision to examine
     */
    public function tree($branch = 'HEAD', $folderPath = '', $rev = 'HEAD') {
        if (!$this->repoLoaded()) return null;

        // Check the last character isnt a / otherwise SVN will return the contents of the folder
        if ($folderPath != '' && $folderPath[strlen($folderPath)-1] == '/') {
            $folderPath = substr($folderPath, 0, strlen($folderPath)-1);
        }

        $out = $this->exec(sprintf('info --xml %s@%s', escapeshellarg($this->repo.$folderPath), escapeshellarg($rev)));
        $xml = simplexml_load_string($out);

        if (!isset($xml->entry)) {
            return false;
        }

        // Init standard return array
        $return = array(
            'type' => (string) $xml->entry['kind'],
            'content' => '',
            'path' => $folderPath
        );

        // Handle file case (I know its a tree function, but we might as well)
        if ($return['type'] == 'file') {
            $return['content'] = $this->show($folderPath, $rev);
        }

        if ($return['type'] == 'dir') {
            $out = $this->exec(sprintf('ls --xml %s@%s', escapeshellarg($this->repo.$folderPath), escapeshellarg($rev)));
            $xml = simplexml_load_string($out);
    
            foreach ($xml->list->entry as $entry) {
                $file = array();
                $file['type'] = (string) $entry['kind'];
                $file['name'] = (string) $entry->name;
                $file['fullpath'] = $folderPath.((string) $entry->name);
                // Get the size if the type is blob
                if ($file['type'] == 'file') {
                    $file['size'] = (string) $entry->size;
                }
                $file['updated'] = gmdate('Y-m-d H:i:s', strtotime((string) $entry->commit->date));
                $file['message'] = '[currently not avaliable]';
                $return['content'][] = $file;
            }
        }
        return $return;
    }

    /*
     * show
     * Return the details of a blob
     *
     * @param $folderPath path to load
     * @param $rev revision to load
     */
    public function show($folderPath = '', $rev = 'HEAD') {
        if (!$this->repoLoaded()) return null;

        return $this->exec(sprintf('cat %s@%s', escapeshellarg($this->repo.$folderPath), escapeshellarg($rev)));
    }

    /*
     * log
     * Return a list of commits
     *
     * @param $branch string the branch to look up
     * @param $limit int a restriction on the number of commits to return
     * @param $offset int an offest for the number restriction
     * @param $filepath string files can be specified to limit log return
     */
    public function log($branch = 'master', $limit = 10, $offset = 0, $filepath = '') {
        if (!$this->repoLoaded()) return null;
    }

    /*
     * showCommit
     * Return a list of commits
     *
     * @param $hash string the hash to look up
     */
    public function showCommit($hash) {
        if (!$this->repoLoaded()) return null;
    }

    /*
     * size
     * Return a list sizes returned by count-objects
     *
     */
    public function size() {
        if (!$this->repoLoaded()) return null;
    }

    /*
     * diff
     * Return the diff for all files altered in a hash
     *
     * @param $hash string commit to look up
     * @param $parent string the parent to compare against
     */
    private function diff($hash, $parent = null) {
        if (!$this->repoLoaded()) return null;
    }

    /**
     * blame
     *
     * @param $filepath string the path to blame
     */
    public function blame($branch, $filepath){
        if (!$this->repoLoaded()) return null;
    }

    /**
     * exec
     * For those times when the built in functions arnt enough
     *
     * @param $command string the command to run
     */
    public function exec($command) {
        if (!$this->repoLoaded()) return null;

        return shell_exec("svn $command");
    }

    /*
     * _commitParent
     * Return the immediate parent of a commit
     *
     * @param $hash string commit to look up
     */
    private function _commitParent($hash) {
        if (!$this->repoLoaded()) return null;
    }

    /*
     * _commitMetadata
     * Return the details for the commit in a hash
     *
     * @param $hash commit to look up
     */
    private function _commitMetadata($hash) {
        if (!$this->repoLoaded()) return null;
    }

}
