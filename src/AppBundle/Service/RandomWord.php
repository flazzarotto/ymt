<?php
/**
 * Created by PhpStorm.
 * User: fabrice
 * Date: 10/11/17
 * Time: 11:43
 */

namespace AppBundle\Service;


class RandomWord
{

    private $dictionaryDir;

    private $dictionaries;

    private $availableLanguages = [];

    public function __construct($root, $dictionaryDir)
    {
        $this->dictionaryDir = preg_replace(['#/{2,}#', '#/+$#'], ['/', ''], $root . '/../' . $dictionaryDir);
        $this->checkLangs();
    }

    public function pickRandomWords($language, $numberOfWords = 1, $returnAsString = true, $delimiter = " ")
    {
        $dict = $this->safeLanguageAccess($language);

        $words = [];

        $random = [];

        for ($rand = mt_rand(0,count($dict)-1); count($words) < $numberOfWords; $rand = mt_rand(0,count($dict)-1)) {
            if (isset($random[$rand])) {
                continue;
            }
            $random[$rand] = true;
            $words[] = preg_replace('#\n$#','',$dict[$rand]);
        }

        if ($numberOfWords > 1 && $returnAsString) {
            $words = implode($delimiter, $words);
        }

        return $words;
    }

    private function checkLangs()
    {

        if (is_dir($this->dictionaryDir)) {
            $dir = opendir($this->dictionaryDir);

            while ($elem = readdir($dir)) {
                if (preg_match('#^([a-z]+)\.txt$#', $elem, $matches)) {
                    $this->availableLanguages[$matches[1]] = true;
                }
            }
            closedir($dir);
        }

    }

    public function getAvailableLanguages()
    {
        return array_keys($this->availableLanguages);
    }

    public function isLanguageAvailable($language)
    {
        return isset($this->availableLanguages[$language]);
    }

    private function safeLanguageAccess($language)
    {
        if (!isset($this->dictionaries[$language])) {
            if ($this->isLanguageAvailable($language)) {
                $this->dictionaries[$language] = file($this->dictionaryDir . "/" . $language . ".txt");
            } else {
                $this->dictionaries[$language] = [''];
            }
        }

        return $this->dictionaries[$language];
    }

}