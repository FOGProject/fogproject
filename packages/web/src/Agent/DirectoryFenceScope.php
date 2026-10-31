<?php
/** Explicit request-local lifetime; no destructor-driven unlock or persistence. */
namespace FOG\Agent;

class DirectoryFenceScope
{
    private $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function close()
    {
        if (null !== $this->id) {
            $id = $this->id;
            $this->id = null;
            DirectoryNameFence::closeScope($id);
        }
    }

    public function finishSQL($success)
    {
        return DirectoryNameFence::finishSQL($this->id, $success);
    }
}
