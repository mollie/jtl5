<?php


namespace Plugin\ws5_mollie\Lib\Controller;

use Plugin\ws5_mollie\lib\Response;

class HelperController extends AbstractController
{


    public static function test(): Response
    {
        return new Response(['test' => true]);
    }

}