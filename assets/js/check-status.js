function checkStatus(productId) {
    $.ajax({
        url: 'check-status.php',
        type: 'POST',
        data: { product_id: productId },
        success: function(response) {
            if (response.success) {
                const row = $(`tr[data-product-id="${productId}"]`);
                updateStatusCell(row, response.status);
                
                // Atualiza informações da transação se disponível
                if (response.transaction) {
                    const transaction = response.transaction;
                    const statusCell = row.find('.status-cell');
                    
                    // Formata o valor para exibição
                    const amount = (transaction.amount / 100).toLocaleString('pt-BR', {
                        style: 'currency',
                        currency: 'BRL'
                    });
                    
                    // Formata as datas
                    const createdAt = new Date(transaction.created_at).toLocaleString('pt-BR');
                    const updatedAt = new Date(transaction.updated_at).toLocaleString('pt-BR');
                    
                    // Atualiza o conteúdo da célula com as informações da transação
                    const transactionInfo = `
                        <div class="transaction-info">
                            <p><strong>Status:</strong> ${getStatusText(response.status)}</p>
                            <p><strong>ID Transação:</strong> ${transaction.id}</p>
                            <p><strong>Valor:</strong> ${amount}</p>
                            <p><strong>Forma de Pagamento:</strong> ${transaction.payment_method}</p>
                            <p><strong>Criado em:</strong> ${createdAt}</p>
                            <p><strong>Atualizado em:</strong> ${updatedAt}</p>
                        </div>
                    `;
                    
                    statusCell.html(transactionInfo);
                }
            } else {
                console.error('Erro ao verificar status:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro na requisição:', error);
        }
    });
}

function updateStatusCell(row, status) {
    const statusCell = row.find('.status-cell');
    statusCell.removeClass().addClass('status-cell ' + getStatusClass(status));
}

function getStatusClass(status) {
    switch (parseInt(status)) {
        case 2: return 'status-paid';
        case 1: return 'status-authorized';
        case 3: return 'status-denied';
        case 10: return 'status-voided';
        case 11: return 'status-refunded';
        case 12: return 'status-pending';
        case 13: return 'status-aborted';
        case 20: return 'status-scheduled';
        default: return 'status-pending';
    }
}

function getStatusText(status) {
    switch (parseInt(status)) {
        case 2: return 'Pago';
        case 1: return 'Autorizado';
        case 3: return 'Negado';
        case 10: return 'Cancelado';
        case 11: return 'Reembolsado';
        case 12: return 'Pendente';
        case 13: return 'Abortado';
        case 20: return 'Agendado';
        default: return 'Aguardando';
    }
}

// Inicia a verificação de status para todos os links na página
$(document).ready(function() {
    $('tr[data-product-id]').each(function() {
        const productId = $(this).data('product-id');
        checkStatus(productId);
        
        // Verifica o status a cada 30 segundos
        setInterval(function() {
            checkStatus(productId);
        }, 30000);
    });
}); 