<?php
function notFilled($name)
{
    if (empty($_POST[$name]))
    {
        return ([$name=>"Поле не заполнено"]);
    }
    else
    {
        return false;
    }
}