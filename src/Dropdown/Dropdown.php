<?php

namespace Ridzhi\Readline\Dropdown;
use Ridzhi\Readline\Console;

/**
 * Class Dropdown
 * @package Ridzhi\Readline\Dropdown
 */
class Dropdown implements DropdownInterface
{

    const POS_START = 0;

    /**
     * @var ThemeInterface
     */
    protected $theme;

    /**
     * @var int actual height
     */
    protected $height = 0;

    /**
     * @var int max height of dropdown
     */
    protected $maxHeight;

    /**
     * @var array list of items
     */
    protected $items = [];

    /**
     * @var int content size
     */
    protected $count = 0;

    /**
     * @var int current pos
     */
    protected $pos = self::POS_START;

    /**
     * @var int dropdown's starting position
     */
    protected $offset = 0;

    /**
     * @var bool if scrolling first -> last
     */
    protected $reverse = false;

    /**
     * @var bool if select someone
     */
    protected $hasFocus = false;


    /**
     * @param array $items Require not empty, for protection consistency API.
     * For empty use NullDropdown implementation
     * @param int $height
     * @param ThemeInterface $theme
     * @throws \InvalidArgumentException
     */
    public function __construct(array $items, int $height, ThemeInterface $theme)
    {
        if (empty($items)) {
            throw new \InvalidArgumentException('Require not empty(for protection consistency API). For empty use NullDropdown implementation');
        }

        $this->maxHeight = $height;
        $this->theme = $theme;
        $this->setItems($items);
    }

    /**
     * @return string Current chosen
     */
    public function getSelect(): string
    {
        return $this->items[$this->pos];
    }

    /**
     * @param int $width Full width of current representation
     * @return string Inline representation of dd
     */
    public function getView(& $width = 0): string
    {
        $dict = $this->getCurrentDict();
        $widthItem = max(array_map('mb_strlen', $dict));
        $scrollbar = ' ';
        $lineWidth = $width = mb_strlen($this->getViewItem('', $widthItem) . $scrollbar);
        $lf = $this->getLF($lineWidth);

        $relativePos = $this->pos - $this->offset;
        $posScroll = $this->getPosScroll();
        $output = '';

        foreach ($dict as $lineNumber => $lineValue) {
            $textStyle = (!$this->hasFocus() || $lineNumber !== $relativePos) ? $this->theme->getText() : $this->theme->getTextActive();
            $line = $this->getViewItem($lineValue, $widthItem);
            $output .= Console::format($line, $textStyle);
            $scrollbarStyle = ($lineNumber !== $posScroll) ? $this->theme->getScrollbar() : $this->theme->getSlider();
            $output .= Console::format($scrollbar, $scrollbarStyle) . $lf;
        }

        return $output;
    }

    /**
     * @return int Height of viewport
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * @return bool If starts navigation by dd
     */
    public function hasFocus(): bool
    {
        return $this->hasFocus;
    }

    /**
     * Looped, 1 -> n
     */
    public function scrollUp()
    {
        if (!$this->hasFocus()) {
            $this->hasFocus = true;
        }

        if ($this->pos === self::POS_START) {
            $this->reverse = true;
            $this->pos = $this->count - 1;
            $this->offset = $this->count - $this->height;
        } else {
            $this->pos--;

            if ($this->pos < $this->offset) {
                $this->offset--;
            }
        }
    }

    /**
     * Looped, n -> 1
     */
    public function scrollDown()
    {
        if (!$this->hasFocus()) {
            $this->hasFocus = true;

            return;
        }

        if ($this->pos === ($this->count - 1)) {
            $this->pos = self::POS_START;
            $this->reverse = false;
            $this->offset = 0;
        } else {
            $this->pos++;

            if ($this->pos > ($this->offset + $this->height - 1)) {
                $this->offset++;
            }
        }
    }

    /**
     * Remove focus, back to default state
     */
    public function reset()
    {
        $this->hasFocus = false;
        $this->reverse = false;
        $this->pos = self::POS_START;
        $this->offset = 0;
    }

    /**
     * @param array $items
     */
    protected function setItems(array $items)
    {
        //reset indexes
        $this->items = array_values($items);
        $this->count = count($items);

        if ($this->count <= $this->maxHeight) {
            $this->height = $this->count;
        } else {
            $this->height = $this->maxHeight;
        }
    }

    /**
     * Normalize by length + padding
     *
     * @param string $word
     * @param int $width
     * @return string
     */
    protected function getViewItem(string $word, int $width): string
    {
        return ' ' . str_pad($word, $width) . ' ';
    }

    /**
     * Linefeed in CSI format.
     * When we are drawn dropdown line, we just use this lf for right positioning cursor
     *
     * @param int $offset
     * @return string
     */
    protected function getLF(int $offset): string
    {
        // down 1 line and left $offset chars
        return "\033[1B" . "\033[{$offset}D";
    }

    /**
     * @return array
     */
    protected function getCurrentDict(): array
    {
        return array_slice($this->items, $this->offset, $this->height);
    }

    /**
     * @return int
     */
    protected function getPosScroll(): int
    {
        if ($this->count <= $this->height) {
            //-1 as not exists lineNumber
            return -1;
        }

        if ($this->offset === 0) {
            return 0;
        }

        if ($this->offset === ($this->count - $this->height)) {
            return $this->height - 1;
        }

        $progress = $this->pos * (100 / $this->count);

        $pos = (int)floor($this->height * $progress / 100);

        if ($pos === 0) {
            return 1;
        }

        if ($pos === ($this->height - 1)) {
            return $pos - 1;
        }

        return $pos;
    }

}