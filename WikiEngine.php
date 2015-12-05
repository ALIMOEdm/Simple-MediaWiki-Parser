class WikiEngine {
    private $lang = 'en';
    private $fish;
    private $limit = 20;

    public function __construct($lang, $fish){
        $this->lang = $lang;
        $this->fish = urlencode($fish);
        $this->fish_source = $fish;

    }

    public function getInfo(){
        $articles = $this->getArticles();
        $result = array();
        $url = '';
        if(count($articles)){
            foreach ($articles as $key => $article) {
                $title = $article['title'];
                switch($this->lang){
                    case 'en':
                        $url = 'https://en.wikipedia.org/wiki/'.$title;
                        $article = $this->getArticle($article);
                        $clear_text = $this->clearWikiString($article);
                        $result = $this->getNeedInfo($clear_text);
                        break(2);
                    case 'fr':
                        $url = 'https://fr.wikipedia.org/wiki/'.$title;
                        if(strtolower($title) == strtolower($this->fish_source)){
                            $article = $this->getArticle($article);
                            $clear_text = $this->clearWikiString($article);
                            $result = $this->getNeedInfo($clear_text);
                            break(2);
                        }
                        break;
                }
            }
        }

        $return = array(
            'url' => $url,
            'result' => $result
        );
        return $return;

    }


    public function getArticles(){
        $url = 'https://'.$this->lang.'.wikipedia.org/w/api.php?action=query&list=search&format=json&srsearch='.$this->fish.'&srwhat=text&redirects=1&limit='.$this->limit;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);

        $res = json_decode($resp, true, 512);
        //list of article
        $article_list = $res['query']['search'];
        return $article_list;
    }

    public function getArticle($article){
        $title = urlencode($article['title']);
        $url_2 = 'https://'.$this->lang.'.wikipedia.org/w/api.php?action=query&prop=revisions&format=json&rvprop=content&redirects=1&titles='.$title;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);
        $res = json_decode($resp, true, 512);
        $pages = $res['query']['pages'];
        $revis = '';
        if(count($pages)){
            foreach($pages as $p){
                $revis = $p['revisions'][0]['*'];
                break;
            }
        }
        return $revis;
    }

    function clearWikiString($str){
        $patterns = array(
            //remove - [[ sometext.jpg sometext ]]
            '/(\[\[\S*\.jpg.*\]\])/mi',
            //remove - <gallery ...>...</gallery>
            '/<gallery[^>]*>.*<\/gallery>(?=[^<]{1})/im',
            //remove - | sometext \n
            '/(^(?:\s*)\|.*$)/mi',
            //remove - { sometext \n
            '/(^(?:\s*)\{.*$)/mi',
            //remove - {{ sometext (except }) }}
            '/(\{\{[^\}]*\}\})/mi',
            //remove - { or }
            '/(\{|\})/mi',
            //remove - ''-//-
            '/(\'){2,}/mi',
            //remove - [ or ]
            '/(\[|\])/mi',
            //remove - * http(s):...
            '/(\*\s(http(s?):)?.*)/mi',
            //remove <ref...> and </ref>
            '/(<\/?ref[^><]*>*)/im',
            //remove - references
            '/([\s|^]?http[^\s]*)/im',
            //remove -  sometext.jpg|text|text-//-
            '/( [^ ]*.jpg(\|.*\|)?\w+)/im',
            //remove -  text|text|text-//- except last
            '/([\S]*\|)/im',
            //remove - | sometext \n
            '/(^(?:\s*)\|.*$)/mi',
        );

        $replacements = array(
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ' ',
            '',
            '',
            '',
            '',
        );

        return preg_replace($patterns, $replacements, $str);
    }

    function getNeedInfo($text){
        /*
        Start format is ->
        ==Aquarium care==
        These fish are venomous, so caution...
        Extract these headers and text into array
        */
        $pattern = '/([=]{2,5}\ *.*\ *[=]{2,5})\s+([^=]*)/im';
        preg_match_all($pattern, $text, $texts);
        $res = array();
        if(count($texts)){
            foreach($texts[1] as $k => $t){
                $body = trim($texts[2][$k]);
                if(isset($texts[2][$k]) && !empty($body) && $body){
                    //remove == from section header
                    preg_match('/([=]{2,5})\ *(.*)\ *\1/im', $t, $matches);
                    $header = isset($matches[2]) && $matches[2] ? $matches[2] : $t;
                    $res[] = array(
                        'header' => $header,
                        'body' => $body,
                    );
                }
            }
        }
        $pattern = '/(.*\s*)/mi';
        preg_match_all($pattern, $text, $texts);
        $start = array();
        if(count($texts)){
            $need = $texts[0];
            if(count($need)){
                foreach($need as $n){
                    if (preg_match("/^==.+/im", $n)) {
                        break;
                    }
                    $start[] = $n;
                }
            }


        }
        if(count($start)) {
            array_unshift($res, array('header' => '', 'body' => implode(' ', $start)));
        }

        return $res;
    }
}
