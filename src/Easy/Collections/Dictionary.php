<?php

/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.easyframework.net>.
 */

namespace Easy\Collections;

use Easy\Collections\IDictionary;
use InvalidArgumentException;

class Dictionary extends CollectionBase implements IDictionary
{

    public function offsetExists($offset)
    {
        return $this->contains($offset);
    }

    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset) == false) {
            throw new InvalidArgumentException(__('The key is not present in the dictionary'));
        }
        return $this->getItem($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->add($offset, $value);
    }

    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    public function add($key, $value)
    {
        if ($key === null) {
            throw new InvalidArgumentException(__("Can't use 'null' as key!"));
        }
        if ($this->contains($key)) {
            throw new InvalidArgumentException(__('That key already exists!'));
        }
        $this->array[$key] = $value;
    }

    public function remove($key)
    {
        if ($this->contains($key) == false) {
            throw new InvalidArgumentException(__('The key is not present in the dictionary'));
        }
        unset($this->array[$key]);
    }

    public function keys()
    {
        return array_keys($this->array);
    }

    public function values()
    {
        return array_values($this->array);
    }

    public function getItem($key)
    {
        return $this->array[$key];
    }

}