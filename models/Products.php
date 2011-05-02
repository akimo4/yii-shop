<?php

class Products extends CActiveRecord
{
	// If at least one product variation has the type 'image', the user needs
	// to upload a image file in order to buy the product. To achieve this,
	// we need to set the 'enctype' to 'multipart/form-data'. This function
	// checks, if the product has a 'image' variation.
	public function hasUpload() {
		foreach($this->variations as $variation)
			if($variation->specification->input_type == 'image')
				return true;

		return false;

	}

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return Shop::module()->productsTable;
	}

	public function beforeValidate() {
		if(Yii::app()->language == 'de')
			$this->price = str_replace(',', '.', $this->price);
		
		return parent::beforeValidate();
	}

	public function rules()
	{
		return array(
			array('title, category_id', 'required'),
			array('product_id, category_id', 'numerical', 'integerOnly'=>true),
			array('title, price, language', 'length', 'max'=>45),
			array('description, specifications', 'safe'),
			array('product_id, title, description, price, category_id', 'safe', 'on'=>'search'),
		);
	}

	public function relations()
	{
		return array(
			'variations' => array(self::HAS_MANY, 'ProductVariation', 'product_id', 'order' => 'position'),
			'variationCount' => array(self::STAT, 'ProductVariation', 'product_id'),
			'orders' => array(self::MANY_MANY, 'Order', 'ShopProductOrder(order_id, product_id)'),
			'category' => array(self::BELONGS_TO, 'Category', 'category_id'),
			'tax' => array(self::BELONGS_TO, 'Tax', 'tax_id'),
			'images' => array(self::HAS_MANY, 'Image', 'product_id'),
			'shopping_carts' => array(self::HAS_MANY, 'ShoppingCart', 'product_id'),
		);
	}

	public function getSpecification($spec) {
		$specs = json_decode($this->specifications, true);

		if(isset($specs[$spec]))
			return $specs[$spec];

		return false;
	}

	public function getImage($image = 0, $thumb = false) {
		if(isset($this->images[$image]))
			return Yii::app()->controller->renderPartial('/image/view', array(
				'model' => $this->images[$image],
				'thumb' => $thumb), true); 
	}

	public function getSpecifications() {
		$specs = json_decode($this->specifications, true);
		return $specs === null ? array() : $specs;
	}

	public function setSpecification($spec, $value) {
		$specs = json_decode($this->specifications, true);

		$specs[$spec] = $value;

		return $this->specifications = json_encode($specs);
	}

	public function setSpecifications($specs) {
		foreach($specs as $k => $v)
			$this->setSpecification($k, $v);
	}

	public function setVariations($variations) {
		$db = Yii::app()->db;
		$db->createCommand()->delete('shop_product_variation',
				'product_id = :product_id', array(
					':product_id' => $this->product_id));

		foreach($variations as $key => $value) {
			if($value['specification_id'] 
					&& isset($value['title']) 
					&& $value['title'] != '') {

				if(isset($value['sign']) && $value['sign'] == '-')
					$value['price_adjustion'] -= 2 * $value['price_adjustion'];


				$db->createCommand()->insert('shop_product_variation', array(
							'product_id' => $this->product_id,
							'specification_id' => $value['specification_id'],
							'position' => @$value['position'] ? $value['position'] : 0,
							'title' => $value['title'],
							'price_adjustion' => @$value['price_adjustion'] ? $value['price_adjustion'] : 0,
							));	
			}
		} 
	} 

		public function getVariations() {
		$variations = array();
		foreach($this->variations as $variation) {
			$variations[$variation->specification_id][] = $variation;
		}		

		return $variations;
	}


	public function attributeLabels()
	{
		return array(
			'tax_id' => Shop::t('Tax'),
			'product_id' => Yii::t('ShopModule.shop', 'Product'),
			'title' => Yii::t('ShopModule.shop', 'Title'),
			'description' => Yii::t('ShopModule.shop', 'Description'),
			'price' => Yii::t('ShopModule.shop', 'Price'),
			'category_id' => Yii::t('ShopModule.shop', 'Category'),
		);
	}

	public function getTaxRate($variations = null, $amount = 1) { 
		if($this->tax) {
			$taxrate = $this->tax->percent;	

			$price = $this->price;

			if($variations)
				foreach($variations as $key => $variation) {
					$price += @ProductVariation::model()->findByPk($variation)->price_adjustion;
				}

			(float) $tax = $price * ($taxrate / 100);

			$tax *= $amount;

			return $tax;
		}
	}

	public function getPrice($variations = null, $amount = 1) {
		$price = (float) $this->price;

		if($this->tax)
			$price *= $this->tax->percent / 100 + 1;

		if($variations)
			foreach($variations as $key => $variation) {
				if(is_numeric($variation))
					$price += @ProductVariation::model()->findByPk($variation)->price_adjustion;
			}


		(float) $price *= $amount;

		return $price;
	}

	public function search()
	{

		$criteria=new CDbCriteria;

		$criteria->compare('product_id',$this->product_id);
		$criteria->compare('title',$this->title,true);
		$criteria->compare('description',$this->description,true);
		$criteria->compare('price',$this->price,true);
		$criteria->compare('category_id',$this->category_id);

		return new CActiveDataProvider('Products', array(
			'criteria'=>$criteria,
		));
	}
}
