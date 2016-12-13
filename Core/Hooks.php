<?php
// It is required for unix signaling
declare(ticks = 1);


/**
 * Hooks controller class based in the observed pattern.
 *
 * @package     Mamuph Hooks
 * @category    Hooks
 * @author      Mamuph Team
 * @copyright   (c) 2015-2016 Mamuph Team
 */

abstract class Core_Hooks
{


    /**
     * @var  Hooks  Singleton instance container
     */
    protected static $_instance = array();


    /**
     * @var array   Hook list
     */
    protected $hooks = [
        'UNIX_SIGHUP'          => [],
        'UNIX_SIGINT'          => [],
        'UNIX_SIGQUIT'         => [],
        'UNIX_SIGILL'          => [],
        'UNIX_SIGTRAP'         => [],
        'UNIX_SIGABRT'         => [],
        //'UNIX_SIGIOT'          => [],
        'UNIX_SIGBUS'          => [],
        'UNIX_SIGFPE'          => [],
        //'UNIX_SIGKILL'         => [],
        'UNIX_SIGUSR1'         => [],
        'UNIX_SIGSEGV'         => [],
        'UNIX_SIGUSR2'         => [],
        'UNIX_SIGPIPE'         => [],
        'UNIX_SIGALRM'         => [],
        'UNIX_SIGTERM'         => [],
        'UNIX_SIGSTKFLT'       => [],
        'UNIX_SIGCLD'          => [],
        'UNIX_SIGCHLD'         => [],
        'UNIX_SIGCONT'         => [],
        //'UNIX_SIGSTOP'         => [],
        'UNIX_SIGTSTP'         => [],
        'UNIX_SIGTTIN'         => [],
        'UNIX_SIGTTOU'         => [],
        'UNIX_SIGURG'          => [],
        'UNIX_SIGXCPU'         => [],
        'UNIX_SIGXFSZ'         => [],
        'UNIX_SIGVTALRM'       => [],
        'UNIX_SIGPROF'         => [],
        'UNIX_SIGWINCH'        => [],
        'UNIX_SIGPOLL'         => [],
        'UNIX_SIGIO'           => [],
        'UNIX_SIGPWR'          => [],
        'UNIX_SIGSYS'          => [],
        'UNIX_SIGBABY'         => [],

        'MAMUPH_INITIALIZED'   => [],
        'MAMUPH_TERMINATED'    => []
    ];



    /**
     * Core_Hooks constructor.
     *
     * @param   bool    $attach_signals     Attach UNIX signals as hooks into this instance
     */
    public function __construct($attach_signals = true)
    {

        // @link http://www.ucs.cam.ac.uk/docs/course-notes/unix-courses/Building/files/signals.pdf
        if ($attach_signals && function_exists('pcntl_signal'))
        {

            foreach (array_keys($this->hooks) as $hookname)
            {

                if (strpos($hookname, 'UNIX_') !== false)
                {
                    $signal = str_replace('UNIX_', '', $hookname);

                    if (defined($signal))
                        pcntl_signal(constant($signal), array($this, 'notify_signal'));

                }
            }
        }

    }



    /**
     * Get the singleton instance of this class.
     *
     * @example
     *
     *     $hooks = Hooks::instance();
     *
     * @param   string  $name   Instance name
     * @return  Hooks
     */
    public static function instance($name = 'default', $attach_signals = true)
    {
        if (empty(Hooks::$_instance[$name]))
        {
            // Create a new instance
            Hooks::$_instance[$name] = new Hooks($attach_signals);
        }

        return Hooks::$_instance[$name];
    }



    /**
     * Notify UNIX signal.
     *
     * Note: This function should be called by the notification method.
     * Do no call this function unless that observes are called manually.
     *
     * @param   $signal
     * @return  void
     */
    public function notify_signal($signal)
    {
        foreach (array_keys($this->hooks) as $hookname)
        {

            if (strpos($hookname, 'UNIX_') !== false)
            {
                $signal_cons = str_replace('UNIX_', '', $hookname);

                if (constant($signal_cons) === $signal)
                {
                    $this->notify($hookname, $signal);
                    break;
                }

            }
        }
    }



    /**
     * Attach an observer to the hook
     *
     * @example
     *
     *      Hooks::instance()->attach('MY_EVENT', function($args) { echo "Hey, event raised"; }, 'my_event');
     *
     *      // Or call method from current class
     *
     *      Hooks::instance()->attach('MY_EVENT', array($this, 'raise_event'), 'my_event');
     *
     *      // Or call a single function
     *
     *      Hooks::instance()->attach('MY_EVENT', 'raise_event', 'my_event');
     *
     * @param string    $hookname   The hook name
     * @param string|callable $method   Method that is called when the hook is raised
     * @param mixed     $id     The attachment ID, by default a random unique ID is assigned by default when false
     * @return void
     */
    public function attach($hookname, $method, $id = false)
    {
        $this->add($hookname);

        if ($id === false)
            $id = uniqid('event_', true);

        $this->hooks[$hookname][$id] = $method;
    }



    /**
     * Detach an observer from a hook
     *
     * @example
     *
     *      // Detach all the observes from a specific hook
     *      Hooks::instance()->detach('MY_EVENT');
     *
     *      // Detach an observer from a specific hook
     *      Hooks::instance()->detach('MY_EVENT', 'my_event');
     *
     *      // Detach one or more observers from a specific hook using the observer method as reference
     *      Hooks::instance()->detach('MY_EVENT', null, 'raise_event');
     *
     * @param   string            $hookname   The hook name
     * @param   string            $id         The attachment ID (Optional)
     * @param   string|callable   $method     The method name (Optional)
     * @return  bool     True when one or more observers are detached
     */
    public function detach($hookname, $id = null, $method = null)
    {

        if (array_key_exists($hookname, $this->hooks))
        {

            if (empty($id) && empty($method))
            {
                $this->hooks[$hookname] = [];
                return true;
            }

            if (!empty($id))
            {
                if (array_key_exists($id, $this->hooks[$hookname]))
                {
                    unset($this->hooks[$hookname][$id]);
                    return true;
                }
            }

            $success = false;

            foreach ($this->hooks[$hookname] as $id => $observer)
            {

                if (is_array($observer))
                {
                    if (array_values($observer) === $method)
                    {
                        unset($this->hooks[$hookname][$id]);
                        $success = true;
                    }

                }
                else if ($observer === $method)
                {
                    unset($this->hooks[$hookname][$id]);
                    $success = true;
                }

            }

            return $success;

        }

        return false;

    }



    /**
     * Check if hook exists and it has attached at least one observer
     *
     * @example
     *
     *      // Check if at least one observer is attached to a hook
     *      Hooks::instance()->was_attached('MY_EVENT');
     *
     *      // Check if a specific observer is attached to a hook (Search by hook-observer ID)
     *      Hooks::instance()->was_attached('MY_EVENT', 'my_event');
     *
     *      // Check if a specific observer is attached to a hook (Search by observer method)
     *      Hooks::instance()->was_attached('MY_EVENT', null, 'raise_event');
     *
     * @param   string      $hookname
     * @param   null        $id
     * @param   null        $method
     * @return  bool
     */
    public function was_attached($hookname, $id = null, $method = null)
    {

        if (array_key_exists($hookname, $this->hooks))
        {

            if (empty($id) && empty($method))
                return empty($this->hooks[$hookname]);


            if (!empty($id))
                return empty($this->hooks[$hookname][$id]);

            $attached = false;

            foreach ($this->hooks[$hookname] as $id => $observer)
            {

                if (is_array($observer))
                {
                    if (array_values($observer) === $method)
                        $attached = true;
                }
                else if ($observer === $method)
                {
                    $attached = true;
                }

            }

            return $attached;

        }

        return false;

    }



    /**
     * Add a new hook to the hooklist
     *
     * @param   string  $hookname   Hook name
     * @return  bool    True when hook is added to the hooklist or false when hook was already added
     */
    public function add($hookname)
    {

        if (!$this->exists($hookname))
        {
            $this->hooks[$hookname] = [];
            return true;
        }

        return false;

    }



    /**
     * Check if hook is available in the hooklist
     *
     * @param   string    $hookname
     * @return  bool
     */
    public function exists($hookname)
    {
        return array_key_exists($hookname, $this->hooks);
    }


    /**
     * Notify or call the observers
     *
     * @example
     *
     *      Hooks::instance()->notify('MY_EVENT', 'argument');
     *
     * @param   string    $hookname
     * @param   mixed     $parameters
     * @return  void
     * @throws  Exception
     */
    public function notify($hookname, $parameters = null)
    {
        if (array_key_exists($hookname, $this->hooks))
        {
            foreach ($this->hooks[$hookname] as $observer)
                call_user_func($observer, $parameters);
        }
    }

}
