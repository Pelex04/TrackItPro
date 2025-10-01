document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.createElement('canvas');
    canvas.id = 'starryBackground';
    canvas.style.position = 'fixed';
    canvas.style.top = '0';
    canvas.style.left = '0';
    canvas.style.zIndex = '-1';
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    document.body.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    let width, height;

    function resizeCanvas() {
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = width;
        canvas.height = height;
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);


    const stars = [];
    for (let i = 0; i < 100; i++) {
        stars.push({
            x: Math.random() * width,
            y: Math.random() * height,
            radius: Math.random() * 2,
            opacity: Math.random(),
            speed: Math.random() * 0.02 + 0.01
        });
    }

   
    const comet = {
        x: -50,
        y: Math.random() * height,
        length: 50,
        speed: 5,
        active: false
    };

    function animate() {
        ctx.clearRect(0, 0, width, height);
        ctx.fillStyle = 'rgba(0, 0, 50, 0.8)';
        ctx.fillRect(0, 0, width, height);

       
        stars.forEach(star => {
            ctx.beginPath();
            ctx.arc(star.x, star.y, star.radius, 0, Math.PI * 2);
            star.opacity += star.speed * (Math.random() > 0.5 ? 1 : -1);
            if (star.opacity < 0) star.opacity = 0;
            if (star.opacity > 1) star.opacity = 1;
            ctx.fillStyle = `rgba(255, 255, 255, ${star.opacity})`;
            ctx.fill();
        });

       
        if (Math.random() < 0.01) comet.active = true;
        if (comet.active) {
            ctx.beginPath();
            ctx.moveTo(comet.x, comet.y);
            ctx.lineTo(comet.x - comet.length, comet.y + comet.length / 2);
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.8)';
            ctx.lineWidth = 2;
            ctx.stroke();
            comet.x += comet.speed;
            if (comet.x > width + comet.length) {
                comet.active = false;
                comet.x = -50;
                comet.y = Math.random() * height;
            }
        }

        requestAnimationFrame(animate);
    }

    animate();
});