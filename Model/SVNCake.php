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

App::import("Vendor", "SVNCake.UnifiedDiff", array("file"=>"UnifiedDiff/Diff.php"));

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
     * @param $goodPractice boolean lets be nice
     * @return boolean true if repo is created
     */
    public function createRepo($base = null, $goodPractice = false) {
        if (file_exists($base)) {
            // Lets talk about logging as some point
            return;
        }

        $return = 0;
        $output = array();
        exec("svnadmin create $base", $output, $return);

        if ($return != 0) {
            // Lets talk about logging as some point
            return;
        }

        if ($goodPractice) {
            // Provide the users with a good repo layout
            $return = 0;
            $output = array();
            exec("svn mkdir file:///$base/trunk file:///$base/tags file:///$base/branches -m'Trunk Tag Branches'", $output, $return);
            
            if ($return != 0) {
                // Lets talk about logging as some point
                return;
            }
        }

        // Copy any hooks in here

        return ($return == 0);
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

        $res = array();

        $out = $this->exec(sprintf(' ls %s@HEAD', escapeshellarg($this->repo.'/branches')));

        if ($out['return'] == 0) {
            foreach (explode("\n", $out['output']) as $entry) {
                if (substr(trim($entry), -1) == '/') {
                    $branch = substr(trim($entry), 0, -1);
                    $res[] = $branch;
                }
            }
        }

        $out = $this->exec(sprintf(' info %s@HEAD', escapeshellarg($this->repo.'/trunk')));

        if ($out['return'] == 0) {
            $res[] = 'trunk';
        }

        return $res;
    }

    /*
     * hasTree
     * Check if repo has a tree
     *
     * @param $branch string the tree to look up
     * @return boolean true if tree exists
     */
    public function hasTree($branch) {
        if (!$this->repoLoaded()) return null;

        if (ucwords($branch) == 'HEAD') return true;

        $out = $this->exec(sprintf('log %s@%s', escapeshellarg($this->repo), escapeshellarg($branch)));

        return ($out['return'] == 0);
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

        if ($out['return'] != 0) {
            return array('type' => 'invalid');
        }

        $xml = simplexml_load_string($out['output']);

        if (!isset($xml->entry)) {
            return array('type' => 'invalid');
        }

        // Init standard return array
        $return = array(
            'type' => (string) $xml->entry['kind'],
            'content' => array(),
            'path' => $folderPath
        );

        // Handle file case (I know its a tree function, but we might as well)
        if ($return['type'] == 'file') {
            $return['content'] = $this->show($folderPath, $rev);
        }

        if ($return['type'] == 'dir') {
            $out = $this->exec(sprintf('ls --xml %s@%s', escapeshellarg($this->repo.$folderPath), escapeshellarg($rev)));
            $xml = simplexml_load_string($out['output']);

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

        $out = $this->exec(sprintf('cat %s@%s', escapeshellarg($this->repo.$folderPath), escapeshellarg($rev)));
        return $out['output'];
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
    public function log($branch = 'HEAD', $limit = 10, $offset = 0, $filepath = '') {
        if (!$this->repoLoaded()) return null;

        if ($branch != 'HEAD' and !preg_match('/^\d+$/', $branch)) {
            // we accept only revisions or HEAD
            $branch = 'HEAD';
        }

        $commits = array();

        $out = $this->exec(sprintf('log --xml -v --limit %s %s@%s', escapeshellarg(($limit+$offset)), escapeshellarg($this->repo.$filepath), escapeshellarg($branch)));
        $xml = simplexml_load_string($out['output']);

        foreach ($xml->logentry as $entry) {
            $commits[] = $this->showCommit((string) $entry['revision']);
        }

        return $commits;
    }

    /*
     * showCommit
     * Return a list of commits
     *
     * @param $hash string the hash to look up
     */
    public function showCommit($hash) {
        if (!$this->repoLoaded()) return null;

        $result['Commit'] = $this->_commitMetadata($hash);
        $result['Commit']['diff'] = $this->diff($hash);

        return $result;
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

        if ($parent == null) {
            $parent = $this->_commitParent($hash);
        }

        $return = array();

        $out = $this->exec(sprintf('diff -c %s %s', escapeshellarg($hash), escapeshellarg($this->repo)));

        $output = Diff::parse($out['output']);

        foreach ($output as $file => $array) {
            $output[$file]['less'] = 0;
            $output[$file]['more'] = 0;

            foreach ($array['hunks'] as $hunk) {
                foreach ($hunk as $line) {
                    if ($line[0] == '-') $output[$file]['less']++;
                    if ($line[0] == '+') $output[$file]['more']++;
                }
            }
        }

        return $output;
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
    public function exec($command, $bare = false) {
        if (!$this->repoLoaded()) return null;

        $svn = ($bare) ? '' : 'svn';
        $return = array();

        $return['out'] = exec("$svn $command", $return['output'], $return['return']);
        $return['output'] = implode("\n", $return['output']);

        return $return;
    }

    /*
     * _commitParent
     * Return the immediate parent of a commit
     *
     * @param $hash string commit to look up
     */
    private function _commitParent($hash) {
        if (!$this->repoLoaded()) return null;

        return ($hash <= 0) ? 0 : ($hash-1);
    }

    /*
     * _commitMetadata
     * Return the details for the commit in a hash
     *
     * @param $branch commit to look up
     */
    private function _commitMetadata($branch) {
        if (!$this->repoLoaded()) return null;

        if ($branch != 'HEAD' and !preg_match('/^\d+$/', $branch)) {
            // we accept only revisions or HEAD
            $branch = 'HEAD';
        }

        $commit = array();

        $out = $this->exec(sprintf('log --xml -v --limit 1 %s@%s', escapeshellarg($this->repo), escapeshellarg($branch)));
        $xml = simplexml_load_string($out['output']);

        $commit['author']['name'] = (string) $xml->logentry->author;
        $commit['author']['email'] = '[currently not avaliable]';
        $commit['date'] = gmdate('Y-m-d H:i:s', strtotime((string) $xml->logentry->date));
        $split = preg_split("[\n\r]", (string) $xml->logentry->msg, 2);
        $commit['subject'] = $split[0];
        $commit['hash'] = (string) $xml->logentry['revision'];
        $commit['body'] = (isset($split[1])) ? trim($split[1]) : '';
        $commit['parent'] = $this->_commitParent($commit['hash']);

        return $commit;
    }

}
