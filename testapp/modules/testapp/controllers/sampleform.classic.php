<?php
/**
* @package     testapp
* @subpackage  testapp module
* @author      Laurent Jouanneau
* @contributor
* @copyright   2005-2007 Laurent Jouanneau
* @link        http://www.jelix.org
* @licence     GNU Lesser General Public Licence see LICENCE file or http://www.gnu.org/licenses/lgpl.html
*/

class sampleFormCtrl extends jController {

  protected function prepareForm($form) {
    $form->deactivate('unwanted');
    $c = $form->getControl('objects_datasource');
    $c->datasource->data = array('ic'=>'ice cream', 'pot'=>'potatoes', 'bean'=>'beans');
    //$form->setReadOnly('listdep2');
  }

  function newform(){
      jForms::destroy('sample');
      // création d'un formulaire vierge
      $form = jForms::create('sample');
      $this->prepareForm($form);
      $rep= $this->getResponse("redirect");
      $rep->action="sampleform:show";
      //$form->setReadOnly('conf');
      //$form->setData('conf', 'browser');
      return $rep;
  }

  function show(){
      // recupère les données du formulaire
      $form = jForms::get('sample');
      if($form == null){
          $form = jForms::create('sample');
          $form->deactivate('unwanted');
      }
      $this->prepareForm($form);

      $rep = $this->getResponse('html');
      $rep->title = 'Form editing';

      $tpl = new jTpl();
      $tpl->assign('form', $form);

      $tpl->assign('builder', $this->param('builder','html'));

      if($this->param('full')) 
          $rep->body->assign('MAIN',$tpl->fetch('sampleformfull'));
      else
          $rep->body->assign('MAIN',$tpl->fetch('sampleform'));
      $rep->body->assign('page_title','forms');

      return $rep;
   }

   function save(){
      // récuper le formulaire
      // et le rempli avec les données reçues de la requête
      $rep= $this->getResponse("redirect");
      
      
      $form = jForms::get('sample');
      $this->prepareForm($form);
      $form->initFromRequest();
      if($form->check())
          $rep->action="sampleform:ok";
      else
          $rep->action="sampleform:show";
      return $rep;
   }

   function ok(){
      $form = jForms::get('sample');

      $rep = $this->getResponse('html');
      $rep->title = 'Form editing';

      if($form){
        $this->prepareForm($form);
        $tpl = new jTpl();
        $tpl->assign('form', $form);
        $rep->body->assign('MAIN',$tpl->fetch('sampleformresult'));
      }else{
        $rep->body->assign('MAIN','<p>The form doesn\'t exist.</p>');
      }
      $rep->body->assign('page_title','formulaires');
      return $rep;
   }

   function destroy(){
      jForms::destroy('sample');
      $rep= $this->getResponse("redirect");
      $rep->action="sampleform:status";
      return $rep;
   }

   function status(){
      $rep = $this->getResponse('html');
      $rep->title = 'Etat des données formulaire';

      $rep->body->assign('page_title','formulaires');

      $content='<h1>Session data of forms</h1>';
      if(isset($_SESSION['JFORMS'])){
          $content.='<pre>'.htmlspecialchars(var_export($_SESSION['JFORMS'],true)).'</pre>';
      }else{
          $content.='<p>There isn\'t any forms...</p>';
      }
      $rep->body->assign('MAIN',$content);
      return $rep;
   }

}
