<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Brazilian Portuguese language strings for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// phpcs:disable moodle.Files.LineLength
defined('MOODLE_INTERNAL') || die();

$string['apikey_clear'] = 'Limpar chave salva';
$string['apikey_cleared'] = 'Chave de API removida.';
$string['apikey_placeholder'] = 'Cole sua chave de API aqui';
$string['apikey_saved'] = 'Chave de API salva.';
$string['autorenew_seasons'] = 'Abrir próxima temporada automaticamente';
$string['autorenew_seasons_desc'] = 'Quando uma temporada encerra, o sistema cria e ativa a próxima automaticamente usando a duração configurada abaixo. A nova temporada herda os limites de XP da temporada encerrada.';
$string['defaultseasonname'] = 'Temporada 1';
$string['event_achievement_earned'] = 'Conquista obtida';
$string['event_cartridge_deleted'] = 'Cartucho de conceitos excluído';
$string['event_cartridge_imported'] = 'Cartucho de conceitos importado';
$string['event_game_completed'] = 'Jogo diário concluído';
$string['event_level_reached'] = 'Novo nível alcançado pelo professor';
$string['event_season_closed'] = 'Temporada de gamificação encerrada';
$string['event_season_created'] = 'Temporada de gamificação criada';
$string['event_streak_broken'] = 'Sequência de atividades interrompida';
$string['event_streak_updated'] = 'Sequência de atividades atualizada';
$string['local/playergames:managecartridges'] = 'Gerenciar cartuchos de conceitos';
$string['local/playergames:manageownkeys'] = 'Gerenciar próprias chaves de API de IA';
$string['local/playergames:playgames'] = 'Jogar jogos diários';
$string['local/playergames:viewdashboard'] = 'Visualizar painel do ecossistema';
$string['local/playergames:viewstaffhud'] = 'Visualizar HUD de gamificação do corpo docente';
$string['mykeys_heading'] = 'Minhas chaves de API de IA';
$string['mykeys_intro'] = 'Suas chaves pessoais têm prioridade sobre a chave compartilhada do hub. Deixe o campo em branco para usar a chave compartilhada.';
$string['mykeys_pagetitle'] = 'Minhas chaves de API de IA';
$string['pluginname'] = 'Player Games';
$string['privacy:metadata'] = 'O plugin Player Games armazena chaves de API de IA pessoais como preferências de usuário e armazenará dados de gamificação em uma fase posterior.';
$string['privacy:pref_gemini_key'] = 'Chave de API do Gemini pessoal armazenada como preferência de usuário.';
$string['privacy:pref_groq_key'] = 'Chave de API do Groq pessoal armazenada como preferência de usuário.';
$string['privacy:pref_key_set'] = 'Chave configurada (valor não exportado por segurança).';
$string['privacy:pref_openai_key'] = 'Chave de API da OpenAI pessoal armazenada como preferência de usuário.';
$string['season_duration_months'] = 'Duração padrão da temporada (meses)';
$string['season_duration_months_desc'] = 'Número de meses para temporadas criadas automaticamente ou pré-preenchidas. Usado na renovação automática e como data de término padrão no formulário de criação de temporada.';
$string['season_setup_heading'] = 'Configurações de temporada';
$string['settings_apikeys_heading'] = 'Chaves de API de IA';
$string['settings_apikeys_heading_desc'] = 'Chaves compartilhadas por todos os plugins Player. Chaves pessoais configuradas por cada usuário têm sempre prioridade.';
$string['settings_gemini_key'] = 'Chave de API do Gemini';
$string['settings_gemini_key_desc'] = 'Chave de API do Google Gemini compartilhada entre todos os plugins Player.';
$string['settings_groq_key'] = 'Chave de API do Groq';
$string['settings_groq_key_desc'] = 'Chave de API do Groq compartilhada entre todos os plugins Player.';
$string['settings_openai_baseurl'] = 'URL base compatível com OpenAI';
$string['settings_openai_baseurl_desc'] = 'URL base para qualquer endpoint compatível com OpenAI (ex.: Ollama local, Azure OpenAI).';
$string['settings_openai_key'] = 'Chave de API da OpenAI';
$string['settings_openai_key_desc'] = 'Chave de API da OpenAI (ou compatível) compartilhada entre todos os plugins Player.';
$string['settings_openai_model'] = 'Nome do modelo';
$string['settings_openai_model_desc'] = 'Modelo usado nos recursos de IA (ex.: gpt-4o-mini, gemma3, llama3).';
$string['task_assign_daily_games'] = 'Atribuir conceitos dos jogos diários';
$string['task_close_expired_seasons'] = 'Encerrar temporadas expiradas';
$string['task_purge_old_scores'] = 'Purgar pontuações antigas';
$string['task_reset_daily_missions'] = 'Resetar missões diárias';
