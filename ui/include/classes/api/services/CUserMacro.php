<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Class containing methods for operations with user macro.
 */
class CUserMacro extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'createglobal' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'updateglobal' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'deleteglobal' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'hostmacro';
	protected $tableAlias = 'hm';
	protected $sortColumns = ['macro'];

	/**
	 * Get UserMacros data.
	 *
	 * @param array $options
	 * @param array $options['groupids'] usermacrosgroup ids
	 * @param array $options['hostids'] host ids
	 * @param array $options['hostmacroids'] host macros ids
	 * @param array $options['globalmacroids'] global macros ids
	 * @param array $options['templateids'] template ids
	 * @param boolean $options['globalmacro'] only global macros
	 * @param boolean $options['selectGroups'] select groups
	 * @param boolean $options['selectHosts'] select hosts
	 * @param boolean $options['selectTemplates'] select templates
	 *
	 * @return array|boolean UserMacros data as array or false if error
	 */
	public function get($options = []) {
		$result = [];
		$userid = self::$userData['userid'];

		$sqlParts = [
			'select'	=> ['macros' => 'hm.hostmacroid'],
			'from'		=> ['hostmacro hm'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$sqlPartsGlobal = [
			'select'	=> ['macros' => 'gm.globalmacroid'],
			'from'		=> ['globalmacro gm'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'					=> null,
			'hostids'					=> null,
			'hostmacroids'				=> null,
			'globalmacroids'			=> null,
			'templateids'				=> null,
			'globalmacro'				=> null,
			'editable'					=> false,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectGroups'				=> null,
			'selectHosts'				=> null,
			'selectTemplates'			=> null,
			'countOutput'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			if ($options['editable'] && !is_null($options['globalmacro'])) {
				return [];
			}
			else {
				$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;

				$userGroups = getUserGroupsByUserId($userid);

				$sqlParts['where'][] = 'EXISTS ('.
						'SELECT NULL'.
						' FROM hosts_groups hgg'.
							' JOIN rights r'.
								' ON r.id=hgg.groupid'.
									' AND '.dbConditionInt('r.groupid', $userGroups).
						' WHERE hm.hostid=hgg.hostid'.
						' GROUP BY hgg.hostid'.
						' HAVING MIN(r.permission)>'.PERM_DENY.
							' AND MAX(r.permission)>='.zbx_dbstr($permission).
						')';
			}
		}

		// global macro
		if (!is_null($options['globalmacro'])) {
			$options['groupids'] = null;
			$options['hostmacroids'] = null;
			$options['triggerids'] = null;
			$options['hostids'] = null;
			$options['itemids'] = null;
			$options['selectGroups'] = null;
			$options['selectTemplates'] = null;
			$options['selectHosts'] = null;
		}

		// globalmacroids
		if (!is_null($options['globalmacroids'])) {
			zbx_value2array($options['globalmacroids']);
			$sqlPartsGlobal['where'][] = dbConditionInt('gm.globalmacroid', $options['globalmacroids']);
		}

		// hostmacroids
		if (!is_null($options['hostmacroids'])) {
			zbx_value2array($options['hostmacroids']);
			$sqlParts['where'][] = dbConditionInt('hm.hostmacroid', $options['hostmacroids']);
		}

		// groupids
		if (!is_null($options['groupids'])) {
			zbx_value2array($options['groupids']);

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['where'][] = dbConditionInt('hg.groupid', $options['groupids']);
			$sqlParts['where']['hgh'] = 'hg.hostid=hm.hostid';
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			$sqlParts['where'][] = dbConditionInt('hm.hostid', $options['hostids']);
		}

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			$sqlParts['from']['macros_templates'] = 'hosts_templates ht';
			$sqlParts['where'][] = dbConditionInt('ht.templateid', $options['templateids']);
			$sqlParts['where']['hht'] = 'hm.hostid=ht.hostid';
		}

		// sorting
		$sqlParts = $this->applyQuerySortOptions('hostmacro', 'hm', $options, $sqlParts);
		$sqlPartsGlobal = $this->applyQuerySortOptions('globalmacro', 'gm', $options, $sqlPartsGlobal);

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
			$sqlPartsGlobal['limit'] = $options['limit'];
		}

		// init GLOBALS
		if (!is_null($options['globalmacro'])) {
			$sqlPartsGlobal = $this->applyQueryFilterOptions('globalmacro', 'gm', $options, $sqlPartsGlobal);
			$sqlPartsGlobal = $this->applyQueryOutputOptions('globalmacro', 'gm', $options, $sqlPartsGlobal);
			$res = DBselect(self::createSelectQueryFromParts($sqlPartsGlobal), $sqlPartsGlobal['limit']);
			while ($macro = DBfetch($res)) {
				if ($options['countOutput']) {
					$result = $macro['rowscount'];
				}
				else {
					$result[$macro['globalmacroid']] = $macro;
				}
			}
		}
		// init HOSTS
		else {
			$sqlParts = $this->applyQueryFilterOptions('hostmacro', 'hm', $options, $sqlParts);
			$sqlParts = $this->applyQueryOutputOptions('hostmacro', 'hm', $options, $sqlParts);
			$res = DBselect(self::createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
			while ($macro = DBfetch($res)) {
				if ($options['countOutput']) {
					$result = $macro['rowscount'];
				}
				else {
					$result[$macro['hostmacroid']] = $macro;
				}
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['hostid', 'type'], $options['output']);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * @param array $globalmacros
	 *
	 * @return array
	 */
	public function createGlobal(array $globalmacros) {
		$this->validateCreateGlobal($globalmacros);

		$globalmacroids = DB::insertBatch('globalmacro', $globalmacros);

		foreach ($globalmacros as $index => &$globalmacro) {
			$globalmacro['globalmacroid'] = $globalmacroids[$index];
		}
		unset($globalmacro);

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_MACRO, $globalmacros);

		return ['globalmacroids' => $globalmacroids];
	}

	/**
	 * @param array $globalmacros
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateCreateGlobal(array &$globalmacros) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['macro']], 'fields' => [
			'macro' =>			['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('globalmacro', 'macro')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
			'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('globalmacro', 'value')],
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'length' => DB::getFieldLength('globalmacro', 'value')]
			]],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('globalmacro', 'description')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $globalmacros, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates($globalmacros);
	}

	/**
	 * @param array $globalmacros
	 *
	 * @return array
	 */
	public function updateGlobal(array $globalmacros) {
		$this->validateUpdateGlobal($globalmacros, $db_globalmacros);

		$upd_globalmacros = [];

		foreach ($globalmacros as $globalmacro) {
			$db_globalmacro = $db_globalmacros[$globalmacro['globalmacroid']];

			$upd_globalmacro = DB::getUpdatedValues('globalmacro', $globalmacro, $db_globalmacro);

			if ($upd_globalmacro) {
				$upd_globalmacros[] = [
					'values'=> $upd_globalmacro,
					'where'=> ['globalmacroid' => $globalmacro['globalmacroid']]
				];
			}
		}

		if ($upd_globalmacros) {
			DB::update('globalmacro', $upd_globalmacros);
		}

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_MACRO, $globalmacros, $db_globalmacros);

		return ['globalmacroids' => array_column($globalmacros, 'globalmacroid')];
	}

	/**
	 * @param array $globalmacros
	 * @param array $db_globalmacros
	 *
	 * @throws APIException if the input is invalid
	 */
	private function validateUpdateGlobal(array &$globalmacros, array &$db_globalmacros = null) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['globalmacroid'], ['macro']], 'fields' => [
			'globalmacroid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'macro' =>			['type' => API_USER_MACRO, 'length' => DB::getFieldLength('globalmacro', 'macro')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT])],
			'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('globalmacro', 'value')],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('globalmacro', 'description')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $globalmacros, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_globalmacros = DB::select('globalmacro', [
			'output' => ['globalmacroid', 'macro', 'value', 'description', 'type'],
			'globalmacroids' => array_column($globalmacros, 'globalmacroid'),
			'preservekeys' => true
		]);

		foreach ($globalmacros as $index => $globalmacro) {
			if (!array_key_exists($globalmacro['globalmacroid'], $db_globalmacros)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		$globalmacros = $this->extendObjectsByKey($globalmacros, $db_globalmacros, 'globalmacroid', ['type']);

		foreach ($globalmacros as $index => &$globalmacro) {
			$db_globalmacro = $db_globalmacros[$globalmacro['globalmacroid']];

			if ($globalmacro['type'] != $db_globalmacro['type'] && $db_globalmacro['type'] == ZBX_MACRO_TYPE_SECRET) {
				$globalmacro += ['value' => ''];
			}

			if (array_key_exists('value', $globalmacro) && $globalmacro['type'] == ZBX_MACRO_TYPE_VAULT) {
				if (!CApiInputValidator::validate(['type' => API_VAULT_SECRET], $globalmacro['value'],
						'/'.($index + 1).'/value', $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
			}
		}
		unset($globalmacro);

		$this->checkDuplicates($globalmacros, $db_globalmacros);
	}

	/**
	 * Check for duplicated macros.
	 *
	 * @param array      $globalmacros
	 * @param array|null $db_globalmacros
	 *
	 * @throws APIException if macros already exists.
	 */
	private function checkDuplicates(array $globalmacros, array $db_globalmacros = null): void {
		$macros = [];

		foreach ($globalmacros as $globalmacro) {
			if ($db_globalmacros === null || (array_key_exists('macro', $globalmacro)
					&& CApiInputValidator::trimMacro($globalmacro['macro'])
						!== CApiInputValidator::trimMacro($db_globalmacros[$globalmacro['globalmacroid']]['macro']))) {
				$macros[] = $globalmacro['macro'];
			}
		}

		if (!$macros) {
			return;
		}

		$db_globalmacros = DB::select('globalmacro', [
			'output' => ['macro']
		]);

		$db_macros = [];

		foreach ($db_globalmacros as $db_globalmacro) {
			$db_macros[CApiInputValidator::trimMacro($db_globalmacro['macro'])] = true;
		}

		foreach ($macros as $macro) {
			if (array_key_exists(CApiInputValidator::trimMacro($macro), $db_macros)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Macro "%1$s" already exists.', $macro));
			}
		}
	}

	/**
	 * @param array $globalmacroids
	 *
	 * @return array
	 */
	public function deleteGlobal(array $globalmacroids) {
		$this->validateDeleteGlobal($globalmacroids, $db_globalmacros);

		DB::delete('globalmacro', ['globalmacroid' => $globalmacroids]);

		$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_MACRO, $db_globalmacros);

		return ['globalmacroids' => $globalmacroids];
	}

	/**
	 * @param array $globalmacroids
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateDeleteGlobal(array &$globalmacroids, array &$db_globalmacros = null) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('You do not have permission to perform this operation.'));
		}

		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $globalmacroids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_globalmacros = DB::select('globalmacro', [
			'output' => ['globalmacroid', 'macro'],
			'globalmacroids' => $globalmacroids,
			'preservekeys' => true
		]);

		foreach ($globalmacroids as $globalmacroid) {
			if (!array_key_exists($globalmacroid, $db_globalmacros)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * @param array $hostmacros
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array &$hostmacros) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['hostid', 'macro']], 'fields' => [
			'hostid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'macro' =>			['type' => API_USER_MACRO, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('hostmacro', 'macro')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT]), 'default' => ZBX_MACRO_TYPE_TEXT],
			'value' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')],
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_MACRO_TYPE_VAULT])], 'type' => API_VAULT_SECRET, 'length' => DB::getFieldLength('hostmacro', 'value')]
			]],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $hostmacros, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkHostPermissions(array_unique(array_column($hostmacros, 'hostid')));
		$this->checkHostDuplicates($hostmacros);
	}

	/**
	 * @param array $hostmacros
	 *
	 * @return array
	 */
	public function create(array $hostmacros) {
		$this->validateCreate($hostmacros);

		$hostmacroids = DB::insert('hostmacro', $hostmacros);

		return ['hostmacroids' => $hostmacroids];
	}

	/**
	 * @param array $hostmacros
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$hostmacros, array &$db_hostmacros = null) {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['hostmacroid']], 'fields' => [
			'hostmacroid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'macro' =>			['type' => API_USER_MACRO, 'length' => DB::getFieldLength('hostmacro', 'macro')],
			'type' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_MACRO_TYPE_TEXT, ZBX_MACRO_TYPE_SECRET, ZBX_MACRO_TYPE_VAULT])],
			'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'value')],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('hostmacro', 'description')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $hostmacros, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_hostmacros = $this->get([
			'output' => ['hostmacroid', 'hostid', 'macro', 'type', 'description'],
			'hostmacroids' => array_column($hostmacros, 'hostmacroid'),
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($hostmacros as $hostmacro) {
			if (!array_key_exists($hostmacro['hostmacroid'], $db_hostmacros)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}

		// CUserMacro::get does not return secret values. Loading directly from the database.
		$options = [
			'output' => ['hostmacroid', 'value'],
			'hostmacroids' => array_keys($db_hostmacros)
		];
		$db_hostmacro_values = DBselect(DB::makeSql('hostmacro', $options));

		while ($db_hostmacro_value = DBfetch($db_hostmacro_values)) {
			$db_hostmacros[$db_hostmacro_value['hostmacroid']] += $db_hostmacro_value;
		}

		$hostmacros = $this->extendObjectsByKey($hostmacros, $db_hostmacros, 'hostmacroid', ['hostid', 'type']);

		foreach ($hostmacros as $index => &$hostmacro) {
			$db_hostmacro = $db_hostmacros[$hostmacro['hostmacroid']];

			if ($hostmacro['type'] != $db_hostmacro['type'] && $db_hostmacro['type'] == ZBX_MACRO_TYPE_SECRET) {
				$hostmacro += ['value' => ''];
			}

			if (array_key_exists('value', $hostmacro) && $hostmacro['type'] == ZBX_MACRO_TYPE_VAULT) {
				if (!CApiInputValidator::validate(['type' => API_VAULT_SECRET], $hostmacro['value'],
						'/'.($index + 1).'/value', $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
			}
		}
		unset($hostmacro);

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['hostid', 'macro']], 'fields' => [
			'hostid' =>	['type' => API_ID],
			'macro' =>	['type' => API_USER_MACRO]
		]];

		if (!CApiInputValidator::validateUniqueness($api_input_rules, $hostmacros, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkHostDuplicates($hostmacros);
	}

	/**
	 * Checks if any of the given host macros already exist on the corresponding hosts. If the macros are updated and
	 * the "hostmacroid" field is set, the method will only fail, if a macro with a different hostmacroid exists.
	 * Assumes the "macro", "hostid" and "hostmacroid" fields are valid.
	 *
	 * @param array  $hostmacros
	 * @param string $hostmacros[]['hostmacroid']
	 * @param string $hostmacros[]['hostid']
	 * @param string $hostmacros[]['macro']        (optional)
	 *
	 * @throws APIException if any of the given macros already exist.
	 */
	private function checkHostDuplicates(array $hostmacros) {
		$macro_names = [];
		$existing_macros = [];
		$user_macro_parser = new CUserMacroParser();

		// Parse each macro, get unique names and, if context exists, narrow down the search.
		foreach ($hostmacros as $hostmacro) {
			if (!array_key_exists('macro', $hostmacro)) {
				continue;
			}

			$user_macro_parser->parse($hostmacro['macro']);

			$macro_name = $user_macro_parser->getMacro();
			$context = $user_macro_parser->getContext();
			$regex = $user_macro_parser->getRegex();

			$macro_names[] = ($context === null && $regex === null) ? '{$'.$macro_name : '{$'.$macro_name.':';
			$existing_macros[$hostmacro['hostid']] = [];
		}

		if (!$existing_macros) {
			return;
		}

		$options = [
			'output' => ['hostmacroid', 'hostid', 'macro'],
			'filter' => ['hostid' => array_keys($existing_macros)],
			'search' => ['macro' => $macro_names],
			'searchByAny' => true,
			'startSearch' => true
		];

		$db_hostmacros = DBselect(DB::makeSql('hostmacro', $options));

		// Collect existing unique macro names and their contexts for each host.
		while ($db_hostmacro = DBfetch($db_hostmacros)) {
			$user_macro_parser->parse($db_hostmacro['macro']);

			$macro_name = $user_macro_parser->getMacro();
			$context = $user_macro_parser->getContext();
			$regex = $user_macro_parser->getRegex();

			$existing_macros[$db_hostmacro['hostid']][$macro_name][$db_hostmacro['hostmacroid']] =
				['context' => $context, 'regex' => $regex];
		}

		// Compare each macro name and context to existing one.
		foreach ($hostmacros as $hostmacro) {
			if (!array_key_exists('macro', $hostmacro)) {
				continue;
			}

			$hostid = $hostmacro['hostid'];

			$user_macro_parser->parse($hostmacro['macro']);

			$macro_name = $user_macro_parser->getMacro();
			$context = $user_macro_parser->getContext();
			$regex = $user_macro_parser->getRegex();

			if (array_key_exists($macro_name, $existing_macros[$hostid])) {
				$has_context = ($context !== null && in_array($context,
					array_column($existing_macros[$hostid][$macro_name], 'context'), true
				));
				$has_regex = ($regex !== null && in_array($regex,
					array_column($existing_macros[$hostid][$macro_name], 'regex'), true
				));
				$is_macro_without_context = ($context === null && $regex === null);

				if ($is_macro_without_context || $has_context || $has_regex) {
					foreach ($existing_macros[$hostid][$macro_name] as $hostmacroid => $macro_details) {
						if ((!array_key_exists('hostmacroid', $hostmacro)
									|| bccomp($hostmacro['hostmacroid'], $hostmacroid) != 0)
								&& $context === $macro_details['context'] && $regex === $macro_details['regex']) {
							$hosts = DB::select('hosts', [
								'output' => ['name'],
								'hostids' => $hostid
							]);

							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Macro "%1$s" already exists on "%2$s".', $hostmacro['macro'], $hosts[0]['name'])
							);
						}
					}
				}
			}
		}
	}

	/**
	 * Update host macros.
	 *
	 * @param array $hostmacros an array of host macros
	 *
	 * @return array
	 */
	public function update($hostmacros) {
		$this->validateUpdate($hostmacros, $db_hostmacros);

		$upd_hostmacros = [];

		foreach ($hostmacros as $hostmacro) {
			$db_hostmacro = $db_hostmacros[$hostmacro['hostmacroid']];

			$upd_hostmacro = DB::getUpdatedValues('hostmacro', $hostmacro, $db_hostmacro);

			if ($upd_hostmacro) {
				$upd_hostmacros[] = [
					'values' => $upd_hostmacro,
					'where' => ['hostmacroid' => $hostmacro['hostmacroid']]
				];
			}
		}

		if ($upd_hostmacros) {
			DB::update('hostmacro', $upd_hostmacros);
		}

		return ['hostmacroids' => array_column($hostmacros, 'hostmacroid')];
	}

	/**
	 * @param array $hostmacroids
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateDelete(array &$hostmacroids) {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $hostmacroids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_hostmacros = $this->get([
			'output' => [],
			'hostmacroids' => $hostmacroids,
			'editable' => true,
			'preservekeys' => true
		]);

		foreach ($hostmacroids as $hostmacroid) {
			if (!array_key_exists($hostmacroid, $db_hostmacros)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	/**
	 * Remove macros from hosts.
	 *
	 * @param array $hostmacroids
	 *
	 * @return array
	 */
	public function delete(array $hostmacroids) {
		$this->validateDelete($hostmacroids);

		DB::delete('hostmacro', ['hostmacroid' => $hostmacroids]);

		return ['hostmacroids' => $hostmacroids];
	}

	/**
	 * Checks if the current user has access to the given hosts and templates. Assumes the "hostid" field is valid.
	 *
	 * @param array $hostids    an array of host or template IDs
	 *
	 * @throws APIException if the user doesn't have write permissions for the given hosts.
	 */
	protected function checkHostPermissions(array $hostids) {
		$count = API::Host()->get([
			'countOutput' => true,
			'hostids' => $hostids,
			'filter' => [
				'flags' => ZBX_FLAG_DISCOVERY_NORMAL
			],
			'editable' => true
		]);

		if ($count == count($hostids)) {
			return;
		}

		$count += API::Template()->get([
			'countOutput' => true,
			'templateids' => $hostids,
			'editable' => true
		]);

		if ($count == count($hostids)) {
			return;
		}

		$count += API::HostPrototype()->get([
			'countOutput' => true,
			'hostids' => $hostids,
			'editable' => true
		]);

		if ($count != count($hostids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		// Added type to query because it required to check macro is secret or not.
		if (!$this->outputIsRequested('type', $options['output'])) {
			$options['output'][] = 'type';
		}

		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($options['output'] != API_OUTPUT_COUNT && $options['globalmacro'] === null) {
			if ($options['selectGroups'] !== null || $options['selectHosts'] !== null || $options['selectTemplates'] !== null) {
				$sqlParts = $this->addQuerySelect($this->fieldId('hostid'), $sqlParts);
			}
		}

		return $sqlParts;
	}

	/**
	 * @inheritdoc
	 */
	protected function applyQueryFilterOptions($table, $alias, array $options, $sql_parts) {
		if (is_array($options['search'])) {
			// Do not allow to search by value for macro of type ZBX_MACRO_TYPE_SECRET.
			if (array_key_exists('value', $options['search'])) {
				$sql_parts['where']['search'] = $alias.'.type!='.ZBX_MACRO_TYPE_SECRET;
				zbx_db_search($table.' '.$alias, [
						'searchByAny' => false,
						'search' => ['value' => $options['search']['value']]
					] + $options, $sql_parts
				);
				unset($options['search']['value']);
			}

			if ($options['search']) {
				zbx_db_search($table.' '.$alias, $options, $sql_parts);
			}
		}

		if (is_array($options['filter'])) {
			// Do not allow to filter by value for macro of type ZBX_MACRO_TYPE_SECRET.
			if (array_key_exists('value', $options['filter'])) {
				$sql_parts['where']['filter'] = $alias.'.type!='.ZBX_MACRO_TYPE_SECRET;
				$this->dbFilter($table.' '.$alias, [
						'searchByAny' => false,
						'filter' => ['value' => $options['filter']['value']]
					] + $options, $sql_parts
				);
				unset($options['filter']['value']);
			}

			if ($options['filter']) {
				$this->dbFilter($table.' '.$alias, $options, $sql_parts);
			}
		}

		return $sql_parts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		if ($options['globalmacro'] === null) {
			$hostMacroIds = array_keys($result);

			/*
			 * Adding objects
			 */
			// adding groups
			if ($options['selectGroups'] !== null && $options['selectGroups'] != API_OUTPUT_COUNT) {
				$res = DBselect(
					'SELECT hm.hostmacroid,hg.groupid'.
						' FROM hostmacro hm,hosts_groups hg'.
						' WHERE '.dbConditionInt('hm.hostmacroid', $hostMacroIds).
						' AND hm.hostid=hg.hostid'
				);
				$relationMap = new CRelationMap();
				while ($relation = DBfetch($res)) {
					$relationMap->addRelation($relation['hostmacroid'], $relation['groupid']);
				}

				$groups = API::HostGroup()->get([
					'output' => $options['selectGroups'],
					'groupids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);
				$result = $relationMap->mapMany($result, $groups, 'groups');
			}

			// adding templates
			if ($options['selectTemplates'] !== null && $options['selectTemplates'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'hostmacroid', 'hostid');
				$templates = API::Template()->get([
					'output' => $options['selectTemplates'],
					'templateids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);
				$result = $relationMap->mapMany($result, $templates, 'templates');
			}

			// adding templates
			if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
				$relationMap = $this->createRelationMap($result, 'hostmacroid', 'hostid');
				$templates = API::Host()->get([
					'output' => $options['selectHosts'],
					'hostids' => $relationMap->getRelatedIds(),
					'preservekeys' => true
				]);
				$result = $relationMap->mapMany($result, $templates, 'hosts');
			}
		}

		return $result;
	}

	protected function unsetExtraFields(array $objects, array $fields, $output) {
		foreach ($objects as &$object) {
			if ($object['type'] == ZBX_MACRO_TYPE_SECRET) {
				unset($object['value']);
			}
		}
		unset($object);

		return parent::unsetExtraFields($objects, $fields, $output);
	}
}
