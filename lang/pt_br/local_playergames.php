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
$string['cartridge_activate'] = 'Ativar';
$string['cartridge_active'] = 'Ativo';
$string['cartridge_add_concept'] = 'Adicionar conceito';
$string['cartridge_ai_categories'] = 'Categorias desejadas';
$string['cartridge_ai_categories_desc'] = 'ex.: Anatomia, Fisiologia, Farmacologia';
$string['cartridge_ai_categories_help'] = 'Separadas por vírgula. Deixe em branco para a IA decidir.';
$string['cartridge_ai_difficulty'] = 'Dificuldade média';
$string['cartridge_ai_difficulty_desc'] = '1 (muito fácil) a 5 (muito difícil). Usado como alvo médio para os conceitos gerados.';
$string['cartridge_ai_generate'] = 'Gerar';
$string['cartridge_ai_heading'] = 'Gerar conceitos com IA';
$string['cartridge_ai_language'] = 'Idioma';
$string['cartridge_ai_nokey'] = 'Nenhuma chave de IA configurada. Adicione uma chave nas configurações de Chaves de API de IA ou em Minhas chaves de IA.';
$string['cartridge_ai_quantity'] = 'Número de conceitos';
$string['cartridge_ai_quantity_desc'] = 'Quantos conceitos gerar (10–100).';
$string['cartridge_ai_topic'] = 'Tópico';
$string['cartridge_ai_topic_desc'] = 'Descreva o tema ou área de conhecimento para os conceitos gerados.';
$string['cartridge_author'] = 'Autor';
$string['cartridge_categories'] = 'Categorias';
$string['cartridge_concept_day_bycategory'] = 'Por categoria (uma por dia da semana)';
$string['cartridge_concept_day_mode'] = 'Seleção do conceito do dia';
$string['cartridge_concept_day_mode_desc'] = 'Seleção aleatória de todos os cartuchos ativos, ou uma categoria fixa por dia da semana.';
$string['cartridge_concept_day_random'] = 'Aleatório';
$string['cartridge_concepts_count'] = 'Conceitos';
$string['cartridge_confirm_delete'] = 'Excluir este cartucho e todos os seus conceitos? Esta ação não pode ser desfeita.';
$string['cartridge_created'] = 'Cartucho salvo.';
$string['cartridge_deactivate'] = 'Desativar';
$string['cartridge_delete'] = 'Excluir';
$string['cartridge_deleted'] = 'Cartucho excluído.';
$string['cartridge_edit_concepts'] = 'Editar conceitos';
$string['cartridge_export'] = 'Exportar JSON';
$string['cartridge_heading'] = 'Cartuchos de conceitos';
$string['cartridge_import_file'] = 'Arquivo JSON';
$string['cartridge_import_heading'] = 'Importar cartucho de JSON';
$string['cartridge_import_success'] = '{$a->count} conceitos importados. Categorias: {$a->categories}. Idioma: {$a->language}.';
$string['cartridge_inactive'] = 'Inativo';
$string['cartridge_language'] = 'Idioma';
$string['cartridge_list_empty'] = 'Nenhum cartucho enviado ainda.';
$string['cartridge_name'] = 'Nome do cartucho';
$string['cartridge_new_create'] = 'Criar cartucho';
$string['cartridge_new_heading'] = 'Ou crie um novo cartucho vazio';
$string['cartridge_pagetitle'] = 'Gerenciar cartuchos de conceitos';
$string['cartridge_preview_heading'] = 'Revisar conceitos gerados';
$string['cartridge_preview_save'] = 'Salvar como novo cartucho';
$string['cartridge_select_to_edit'] = 'Selecione um cartucho na lista acima e clique em "Editar conceitos" para gerenciar seus conceitos.';
$string['cartridge_tab_ai'] = 'Gerar com IA';
$string['cartridge_tab_editor'] = 'Editor manual';
$string['cartridge_tab_import'] = 'Importar JSON';
$string['cartridge_timemodified'] = 'Modificado em';
$string['cartridge_uploaded'] = 'Criado em';
$string['cartridge_uploadedby'] = 'Enviado por';
$string['category_add'] = 'Adicionar categoria';
$string['category_confirm_delete'] = 'Excluir esta categoria? Os conceitos ficarão sem categoria.';
$string['category_delete'] = 'Excluir categoria';
$string['category_deleted'] = 'Categoria excluída.';
$string['category_heading'] = 'Categorias';
$string['category_list_empty'] = 'Nenhuma categoria ainda. Adicione uma abaixo.';
$string['category_name'] = 'Nome da categoria';
$string['category_rename'] = 'Renomear';
$string['category_saved'] = 'Categoria salva.';
$string['concept_category'] = 'Categoria';
$string['concept_definition'] = 'Definição';
$string['concept_delete'] = 'Excluir conceito';
$string['concept_deleted'] = 'Conceito excluído.';
$string['concept_difficulty'] = 'Dificuldade';
$string['concept_edit'] = 'Editar';
$string['concept_list_empty'] = 'Nenhum conceito neste cartucho ainda. Adicione o primeiro abaixo.';
$string['concept_save'] = 'Salvar';
$string['concept_saved'] = 'Conceito salvo.';
$string['concept_term'] = 'Termo';
$string['defaultseasonname'] = 'Temporada 1';
$string['error_ai_request_failed'] = 'Falha na requisição à IA: {$a}';
$string['error_cartridge_invalid_json'] = 'O arquivo enviado não contém JSON válido.';
$string['error_cartridge_missing_field'] = 'Campo obrigatório ausente no cartucho: {$a}';
$string['error_cartridge_no_concepts'] = 'O cartucho não contém conceitos.';
$string['error_cartridge_nofile'] = 'Nenhum arquivo foi enviado.';
$string['error_cartridge_notfound'] = 'Cartucho não encontrado.';
$string['error_cartridge_tooconcepts'] = 'O cartucho excede o número máximo de conceitos ({$a}).';
$string['error_category_duplicate'] = 'Já existe uma categoria com este nome neste cartucho.';
$string['error_category_empty_name'] = 'O nome da categoria não pode estar vazio.';
$string['error_category_notfound'] = 'Categoria não encontrada.';
$string['error_concept_empty_definition'] = 'A definição do conceito não pode estar vazia.';
$string['error_concept_empty_term'] = 'O termo do conceito não pode estar vazio.';
$string['error_concept_invalid_difficulty'] = 'A dificuldade deve ser um número entre 1 e 5.';
$string['error_no_ai_key'] = 'Nenhuma chave de IA configurada. Adicione uma chave nas configurações de Chaves de API de IA.';
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
$string['settings_games_heading'] = 'Configurações de jogos';
$string['settings_games_heading_desc'] = 'Configure os jogos diários disponíveis para o corpo docente.';
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
$string['settings_wordle_maxlen'] = 'Comprimento máximo de termo para PlayerGuess';
$string['settings_wordle_maxlen_desc'] = 'Termos mais longos que este valor são excluídos dos sorteios do PlayerGuess. Padrão: 8.';
$string['settings_wordle_minlen'] = 'Comprimento mínimo de termo para PlayerGuess';
$string['settings_wordle_minlen_desc'] = 'Termos mais curtos que este valor são excluídos dos sorteios do PlayerGuess. Padrão: 4.';
$string['task_assign_daily_games'] = 'Atribuir conceitos dos jogos diários';
$string['task_close_expired_seasons'] = 'Encerrar temporadas expiradas';
$string['task_purge_old_scores'] = 'Purgar pontuações antigas';
$string['task_reset_daily_missions'] = 'Resetar missões diárias';
