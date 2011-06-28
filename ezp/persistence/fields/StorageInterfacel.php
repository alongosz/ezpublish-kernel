<?php
namespace ezp\persistence\fields;
/**
 * @package ezp.persistence.fields
 */
interface StorageInterfacel 
{

	/**
	 * @return int
	 * @ReturnType int
	 */
	public function typeHint();

	/**
	 * @param array data
	 * @param ezp.persistence.content.values.ContentField field
	 * @ParamType data array
	 * @ParamType field ezp.persistence.content.values.ContentField
	 */
	public function setValue(array_112 $data, ContentField $field);

	/**
	 * @param int filedId
	 * @param value
	 * @return boolean
	 * @ParamType filedId int
	 * 
	 * @ReturnType boolean
	 */
	public function storeFieldData($filedId, $value);

	/**
	 * @param int fieldId
	 * @ParamType fieldId int
	 */
	public function getFieldData($fieldId);

	/**
	 * @param array fieldId
	 * @return boolean
	 * @ParamType fieldId array
	 * @ReturnType boolean
	 */
	public function deleteFieldData(array_113 $fieldId);

	/**
	 * @return bool
	 * @ReturnType bool
	 */
	public function hasFieldData();

	/**
	 * @param int fieldId
	 * @ParamType fieldId int
	 */
	public function copyFieldData($fieldId);

	/**
	 * @param int fieldId
	 * @ParamType fieldId int
	 */
	public function getIndexData($fieldId);
}
?>