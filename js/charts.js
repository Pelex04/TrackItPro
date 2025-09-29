document.addEventListener('DOMContentLoaded', () => {
    Object.keys(progressData).forEach(habitId => {
        const data = progressData[habitId];
        const ctx = document.getElementById(`chart_${habitId}`).getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.completions.map(c => c.date),
                datasets: [{
                    label: `${data.name} Completions`,
                    data: data.completions.map(c => c.completions),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Completions' } },
                    x: { title: { display: true, text: 'Date' } }
                }
            }
        });
    });
});