<?php
$demoProducts=array_filter($products,static fn(array $product): bool=>!empty($product['is_demo'])&&empty($product['active']));
if($demoProducts&&in_array($user['role'],['owner','manager'],true)): ?>
<article class="cartao aviso informativo" role="status">
    <strong>Seu primeiro produto está pronto para personalizar.</strong>
    <p><?= e(reset($demoProducts)['name']) ?> está em rascunho e não aparece para os consumidores. O preço e a descrição são exemplos: revise-os e adicione uma foto real antes de ativar o produto.</p>
    <a class="botao primario" href="/admin?section=products#product-<?= (int)reset($demoProducts)['id'] ?>">Editar produto de exemplo</a>
    <form method="post" action="/admin/product/discard-demo">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)reset($demoProducts)['id'] ?>">
        <button class="botao" type="submit">Excluir apenas este exemplo</button>
    </form>
</article>
<?php endif; ?>

