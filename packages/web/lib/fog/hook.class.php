<?php
abstract class Hook extends Event {
    public function reportTypes($arguments) {
        $arguments['types'][$this->node] = 4;
    }
}
