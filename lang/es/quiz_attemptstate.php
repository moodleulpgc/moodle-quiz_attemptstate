<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'quiz_attemptstate', language 'en'
 *
 * @package   quiz_attemptstate
 * @copyright Enrique Castro @ULPGC
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Gestionar estados de intentos';
$string['additionalattempts'] = 'Intentos adicionales que se permitirán al estudiante';
$string['additionalattempts_help'] = 'Este es un número de intentos adicionales a los ya realizados, 
no tiene efecto si el Cuestionario está configurado para intentos ilimitados.';
$string['attemptsclosed'] = '{$a} intentos finalizados';
$string['attemptsextended'] = '{$a} intentos reabiertos y extendidos';
$string['attemptsnewed'] = '{$a} nuevos intentos agregados';
$string['attemptstate'] = 'Gestionar estados de intentos';
$string['attemptstatereport'] = 'Gestionar estados de intentos';
$string['attemptstate:componentname'] = 'Gestionar estados de intentos';
$string['attemptstate:newattempt'] = 'Crear un nuevo intento para otro usuario';
$string['attemptstate:view'] = 'Ver el Informe';
$string['attemptstate:reopen'] = 'Reabrir intentos cerrados';
$string['attemptstatefilename'] = 'Estados';
$string['column_attempt_actions'] = 'Acciones';
$string['column_attempt_answers'] = 'Respuestas';

$string['closeattempt'] = 'Finalizar intento';
$string['editoverride'] = 'Extensión de plazos';
$string['event_attempt_closed'] = 'Intento cerrado';
$string['event_attempt_extended'] = 'Intento reabierto';
$string['event_attempt_closed'] = 'Nuevo Intento generado de anterior';
$string['extendattempt'] = 'Extender intento';
$string['newattempt'] = 'Nuevo intento';

$string['closeselected'] = 'Finalizar los intentos marcados';
$string['extendselected'] = 'Extender los intentos marcados';
$string['closeattemptcheck'] = 'Ha solicitado finalizar un intento del estudiante {$a}.<br />¿Desea continuar??';
$string['closeattemptscheck'] = 'Ha solicitado finalizar los intentos seleccionados.<br />¿Desea continuar??';
$string['extendattemptcheck'] = 'Ha solicitado Extender los intentos seleccionados.<br />¿Desea continuar??';
$string['newattemptselected'] = 'Crear nuevo intento para marcados';
$string['newattemptcheck'] = 'Ha solicitado Crear un nuevo intento para los usuarios seleccionados.<br />¿Desea continuar??';

$string['privacy:metadata'] = 'El componente "Gestionar estados de intentos" no almacena nigún dato personal por si mismo. 
Solo proporcioan otro interfaz para visualizar gestionar los datos generados por la actividad Cuestionario.';
$string['question_notanswered'] = '{$a} sin respuesta';
$string['question_invalid'] = '{$a} inválidas';
$string['question_lastslot'] = 'Última pregunta: {$a->num} a las {$a->time}';
$string['reopenenabled'] = 'Permitir reabrir intentos';
$string['reopenenabled_desc'] = 'Si se activa, entonces los intentos finalizados o abandonados pueden ser reabiertos al estado "En curso".';
$string['showuserinfo'] = 'Información a mostrar para identificación de usuarios';
