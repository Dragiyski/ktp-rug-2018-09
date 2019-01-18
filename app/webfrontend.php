<?php

require_once __DIR__ . '/util.php';
require_once __DIR__ . '/solver.php';
require_once __DIR__ . '/reader.php';
require_once __DIR__ . '/formatter.php';
require_once __DIR__ . '/expert-system.php';

function _encode($data) {
    return base64_encode(gzcompress(serialize($data)));
}

function _decode($data) {
    return unserialize(gzuncompress(base64_decode($data)));
}

class WebLogger implements Logger {
    public $messages = array(array());

    public function __wakeup() {
        $this->messages[] = array();
    }

    public function write($format, $arguments, $level) {
        $arguments = array_map(
            function ($arg) {
                return '<tt>' . Template::html(to_debug_string($arg)) . '</tt>';
            },
            $arguments
        );

        $this->messages[count($this->messages) - 1][] = [$level, vsprintf($format, $arguments)];
    }
}

class WebFrontend {
    private $log;

    private $solver;

    private $state;

    private $kb_file;

    public function __construct($kb_file) {
        $this->kb_file = $kb_file;
    }

    public function main() {
        $domain = null;

        $state = null;

        $log = $this->getLog();

        $solver = new Solver($log);

        try {
            $domain = $this->getDomain();

            $state = $this->getState($domain);

            if (isset($_POST['answer'])) {
                if(is_array($_POST['answer'])) {
                    foreach($_POST['answer'] as $key => $value) {
                        if(empty($value)) {
                            $value = STATE_UNDEFINED;
                        }
                        $state->facts[$key] = $value;
                    }
                } else {
                    $state->apply(_decode($_POST['answer']));
                }
            }
            switch ($domain->algorithm) {
            case 'backward-chaining':
                $step = $solver->backwardChain($domain, $state);
                break;

            case 'forward-chaining':
                $step = $solver->forwardChain($domain, $state);
                break;

            default:
                throw new RuntimeException("Unknown inference algorithm. Please choose 'forward-chaining' or 'backward-chaining'.");
            }

            if ($step instanceof AskedQuestion) {
                $page = new Template('templates/question.phtml');
                $page->question = $step->question;
                $page->skippable = $step->skippable;
            } elseif (!isset($state->facts['price'])) {
                /* @var $state KnowledgeState */
                    $page = new Template('templates/price.phtml');
                    $page->fact = 'price';
            } elseif (!isset($state->facts['diet'])) {
                /* @var $state KnowledgeState */
                $page = new Template('templates/diet.phtml');
                $page->fact = 'diet';
            } else {
                $page = expert_advice_product($domain, $state);
            }
        } catch (Exception | Error $e) {
            $page = new Template('templates/exception.phtml');
            $page->exception = $e;
        }

        $page->domain = $domain;
        $page->state = $state;
        $page->log = $log;

        echo $page->render();
    }

    private function getDomain() {
        $reader = new KnowledgeBaseReader();
        return $reader->parse($this->kb_file);
    }

    private function getState($domain) {
        if (isset($_POST['state'])) {
            return _decode($_POST['state']);
        } else {
            return $this->createNewState($domain);
        }
    }

    private function createNewState($domain) {
        $state = KnowledgeState::initializeForDomain($domain);

        if (!empty($_GET['goals'])) {
            $state->goalStack = new Stack();

            foreach (explode(',', $_GET['goals']) as $goal) {
                $state->goalStack->push($goal);
            }
        }

        return $state;
    }

    private function getLog() {
        if (isset($_POST['log'])) {
            return _decode($_POST['log']);
        } else {
            return new WebLogger();
        }
    }
}

// if (!isset($_GET['kb']) || !preg_match('/^[a-zA-Z0-9_\-\.]+\.xml$/i', $_GET['kb'])) {
// 	header('Location: index.php');
// 	exit;
// }

// header('Content-Type: text/html; charset=UTF-8');
// $frontend = new WebFrontend(first_found_path(array(
// 	'./' . $_GET['kb'],
// 	'../knowledgebases/' . $_GET['kb']
// )));
// $frontend->main();
