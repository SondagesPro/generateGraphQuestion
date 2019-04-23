generateGraphQuestion
=====================

A plugin for LimeSurvey to generate graph from question in question answer of the survey using data URI. The plugin produce radar graph.

## Installation

### Via GIT
- Go to your LimeSurvey Directory
- Clone in plugins/generateGraphQuestion directory `git clone https://git.framasoft.org/SondagePro-LimeSurvey-plugin/generateGraphQuestion.git generateGraphQuestion`

### Via ZIP dowload
- Download <http://extensions.sondages.pro/IMG/auto/generateGraphQuestion.zip>
- Extract : `unzip generateGraphQuestion.zip`
- Move the directory to  plugins/ directory inside LimeSUrvey


## Documentation

- Activate the plugin
- Create a long text question type
- Use the new attribute to set label and value by other question code
    - One question code on each line
    - Label are question text (or sub question value for multiple question)
    - Value are question value
- The graph title was the question text
- The graph image was save as question answer in base 64 encoded format.
- You can use Expression Manager in title or label

_tip_ You can use Equation question type for the label and value.

### Show the graph

There is 2 solution to show the graph :

1. You can show it directly in the question, answer is always hidden.
   Use `[Self.img]` if you want to include img with source in the question text or help.
2. Graph are generated when needed, when the question must be shown even if hidden by the hide attribute.
   Then you can use directly (if question code are graph) : `<img src='{graph.NAOK}' />` to include the graph in the survey.

### Adapt the graph

The generated graph have only one option in question setting : the total size of the grap in pixel.
You can update all the default setting with a xml file in template upload file directory or survey uploaded files directory.

The plugin read the default settings and, if exist, `graphQuestion.xml` and `graphQuestionRadar.xml` file in template directory, you can replace default value here.
The plugin look at survey uploaded files to get `graphQuestion.xml`, `graphQuestionRadar.xml` and `graphQuestionQuestionCode.xml` (replaceQuestionCode by the question code of the graph question) and add it in graph configuration.

You can update color, font size and some margin for the graph wrapper and for the header. Font used use included LimeSurvey system to choose the best font for your language, you can set it globally in your config file.

You can update all part of the chart part, see [pChart documentation](http://wiki.pchart.net/doc.draw.radar.html) for available settings.

**An example of such file**

````
<?xml version="1.0" encoding="UTF-8"?>
<graph>
  <fontsize>12</fontsize>
  <chart>
    <Layout>RADAR_LAYOUT_STAR</Layout>
    <DrawPoly>TRUE</DrawPoly>
    <WriteValues>TRUE</WriteValues>
    <BackgroundGradient>
      <StartR>255</StartR>
      <StartG>255</StartG>
      <StartB>255</StartB>
      <StartAlpha>100</StartAlpha>
      <EndR>207</EndR>
      <EndG>227</EndG>
      <EndB>125</EndB>
      <EndAlpha>50</EndAlpha>
    </BackgroundGradient>
  </chart>
</graph>
````

## Home page & Copyright
- HomePage <http://extensions.sondages.pro/>
- Copyright © 2017 Denis Chenu <https://sondages.pro>
- Copyright © 2017 Réseau en scène Languedoc-Roussillon <https://www.reseauenscene.fr/>

Distributed under [GNU AFFERO GENERAL PUBLIC LICENSE Version 3](http://www.gnu.org/licenses/agpl.txt) licence

### Included Library
- [pChart2](http://www.pchart.net) Copyright © 2014 Jean-Damien POGOLOTTI in [GNU GENERAL PUBLIC LICENSE Version 3](http://www.gnu.org/licenses/gpl.txt) licence
