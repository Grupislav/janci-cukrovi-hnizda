document.addEventListener('DOMContentLoaded', () => {
    const productSelect = document.getElementById('product-select');
    const sizeSelect = document.getElementById('size-select');
    const quantityInput = document.getElementById('quantity-input');
    const addItemBtn = document.getElementById('add-item-btn');
    const orderItemsContainer = document.getElementById('order-items-container');
    const orderForm = document.getElementById('order-form');
    const formMessage = document.getElementById('form-message');
    const totalPriceDisplay = document.getElementById('total-price-display');

    let orderItems = [];
    let itemIdCounter = 0;

    // Funkce pro aktualizaci dropdownu s velikostmi
    const updateSizeOptions = () => {
        const productId = productSelect.value;
        sizeSelect.innerHTML = '<option value="">-- Vyberte velikost --</option>';
        sizeSelect.disabled = true;

        if (productId && productSizesMap[productId]) {
            productSizesMap[productId].forEach(size => {
                const option = document.createElement('option');
                option.value = size.id;
                // Zobrazíme cenu rovnou v dropdownu pro přehlednost
                option.textContent = `${size.name} (${size.price} Kč)`;
                sizeSelect.appendChild(option);
            });
            sizeSelect.disabled = false;
        }
    };

    // Funkce pro formátování ceny
    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('cs-CZ', { style: 'currency', currency: 'CZK' }).format(amount);
    };

    // Funkce pro překreslení seznamu objednaných položek A VÝPOČET CENY
    const renderOrderItems = () => {
        let grandTotal = 0;

        if (orderItems.length === 0) {
            orderItemsContainer.innerHTML = '<p>Zatím nebyly přidány žádné položky.</p>';
            totalPriceDisplay.textContent = formatCurrency(0);
            return;
        }

        orderItemsContainer.innerHTML = '';
        orderItems.forEach(item => {
            const lineTotal = item.unitPrice * item.quantity;
            grandTotal += lineTotal;

            const itemDiv = document.createElement('div');
            itemDiv.classList.add('order-item');
            itemDiv.dataset.itemId = item.id;
            itemDiv.innerHTML = `
                <span><strong>${item.productName}</strong> (${item.sizeName})</span>
                <span>${item.quantity} ks × ${formatCurrency(item.unitPrice)}</span>
                <span style="font-weight: bold;">${formatCurrency(lineTotal)}</span>
                <button type="button" class="remove-item-btn" title="Odebrat položku">×</button>
            `;
            orderItemsContainer.appendChild(itemDiv);
        });

        // Aktualizujeme zobrazení celkové ceny
        totalPriceDisplay.textContent = formatCurrency(grandTotal);
    };

    // Event Listenery
    productSelect.addEventListener('change', updateSizeOptions);
    productSelect.addEventListener('input', updateSizeOptions);

    addItemBtn.addEventListener('click', () => {
        const productId = productSelect.value;
        const sizeId = sizeSelect.value;
        const quantity = parseInt(quantityInput.value, 10);

        if (!productId || !sizeId) {
            alert('Prosím, vyberte produkt a velikost.');
            return;
        }
        if (isNaN(quantity) || quantity < 1 || quantity > 10) {
            alert('Množství musí být celé číslo v rozmezí od 1 do 10.');
            return;
        }

        const productName = productSelect.options[productSelect.selectedIndex].text;
        
        // Najdeme vybranou velikost v naší mapě, abychom získali cenu
        const selectedSizeData = productSizesMap[productId].find(s => s.id == sizeId);
        if (!selectedSizeData) {
            alert('Došlo k chybě, velikost nenalezena.');
            return;
        }
        
        const unitPrice = parseFloat(selectedSizeData.price);
        const sizeName = selectedSizeData.name; // Název velikosti bez ceny

        const existingItem = orderItems.find(item => item.productId === productId && item.sizeId === sizeId);
        if (existingItem) {
            alert('Tato položka již byla přidána.');
            return;
        }

        orderItems.push({
            id: itemIdCounter++,
            productId,
            productName: productSelect.options[productSelect.selectedIndex].text.split(' (')[0], // Očistíme název produktu
            sizeId,
            sizeName,
            quantity,
            unitPrice // ULOŽÍME JEDNOTKOVOU CENU
        });

        renderOrderItems();
    });

    orderItemsContainer.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-item-btn')) {
            const itemIdToRemove = parseInt(e.target.closest('.order-item').dataset.itemId, 10);
            orderItems = orderItems.filter(item => item.id !== itemIdToRemove);
            renderOrderItems(); // Přepočítáme cenu po odebrání
        }
    });

    orderForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        // ... zbytek odesílacího kódu je stejný ...
        // Data o ceně (unitPrice) jsou již součástí objektu `orderItems`,
        // takže se automaticky odešlou na server.
        
        formMessage.textContent = '';
        const fullName = document.getElementById('full-name').value;
        const email = document.getElementById('email').value;
        const orderNotes = document.getElementById('order-notes').value;

        if (orderItems.length === 0) {
            formMessage.textContent = 'Do objednávky musíte přidat alespoň jednu položku.';
            formMessage.className = 'error';
            return;
        }

        const orderData = {
            fullName,
            email,
            items: orderItems,
            notes: orderNotes
        };

        try {
            const response = await fetch('send_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(orderData)
            });
            const result = await response.json();
            if (result.success) {
                formMessage.textContent = 'Objednávka byla úspěšně odeslána! Co nevidět se ozvu.';
                formMessage.className = 'success';
                orderForm.reset();
                document.getElementById('order-notes').value = '';
                orderItems = [];
                renderOrderItems();
                updateSizeOptions();
            } else {
                throw new Error(result.message || 'Došlo k chybě při odesílání. Zkuste to prosím později nebo mě kontaktujte na čísle 777 367 942.');
            }
        } catch (error) {
            formMessage.textContent = `Chyba: ${error.message}`;
            formMessage.className = 'error';
        }
    });

    // Počáteční vykreslení
    renderOrderItems();
    // (Lightbox a další kód, který jste přidal, zde zůstává)
    const gallery = new SimpleLightbox('.gallery-container a', {});
});