ALTER TABLE product_reviews
  ADD UNIQUE KEY uq_review_order_product (order_id,product_id);

