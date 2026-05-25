<?php
namespace MPC\Enums;

enum SlotPosition: string
{
    case START = 'start';
    case END = 'end';
    case CENTER = 'center';
    case CENTER_TOP = 'center-top';
    case CENTER_BOTTOM = 'center-bottom';
    case END_TOP = 'end-top';
    case END_BOTTOM = 'end-bottom';
}
