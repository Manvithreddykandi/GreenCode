let timeChart, energyChart;
window.originalMetrics = null;

function updateCharts(originalData, optimizedData = null) {
    const labels = optimizedData ? ['Original', 'Optimized'] : ['Original'];
    const colors = ['#f44336', '#4CAF50'];

    // 1. Map strings to the Big O Complexity Board
    document.getElementById("complexityBoard").style.display = "flex";
    document.getElementById("origTimeO").innerText = originalData.time_o;
    document.getElementById("origSpaceO").innerText = originalData.space_o;
    
    if (optimizedData) {
        document.getElementById("optTimeO").innerText = optimizedData.time_o;
        document.getElementById("optSpaceO").innerText = optimizedData.space_o;
    }

    // 2. Map absolute numbers to the Chart.js Canvases
    const chartConfig = (id, label, dataKey) => {
        const ctx = document.getElementById(id).getContext('2d');
        const dataValues = [originalData[dataKey]];
        if (optimizedData) dataValues.push(optimizedData[dataKey]);

        if (window[id + 'Obj']) window[id + 'Obj'].destroy();

        window[id + 'Obj'] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: dataValues,
                    backgroundColor: colors
                }]
            },
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });
    };

    chartConfig('timeChart', 'Execution Time (Seconds)', 'time_sec');
    chartConfig('energyChart', 'Energy Consumed (Joules)', 'energy_joules');
}

async function analyzeCode() {
    const code = document.getElementById("inputCode").value;
    if (!code) return alert("Paste code first!");

    document.getElementById("analyzeBtn").innerText = "Analyzing...";

    try {
        const response = await fetch("optimize.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ code: code, mode: 'analyze' })
        });
        
        const data = await response.json();
        if (data.error) {
            alert("Analysis Failed: " + data.error);
            document.getElementById("analyzeBtn").innerText = "1. Analyze Code";
            return;
        }
        // NEW: Display the exact CPU Name detected by PowerShell WMI
        document.getElementById("cpuNameDisplay").innerText = data.cpu_name;
        
        window.originalMetrics = data.metrics;
        updateCharts(window.originalMetrics);

        const optBtn = document.getElementById("optimizeBtn");
        optBtn.disabled = false;
        optBtn.style.display = "inline-block";
        optBtn.style.opacity = "1";
        optBtn.style.cursor = "pointer";

        document.getElementById("analyzeBtn").innerText = "1. Analyze Code";
        
    } catch (err) {
        alert("Fatal Error. Check console.");
        document.getElementById("analyzeBtn").innerText = "1. Analyze Code";
    }
}

async function optimizeCode() {
    const code = document.getElementById("inputCode").value;
    document.getElementById("optimizeBtn").innerText = "Optimizing...";
    
    try {
        const response = await fetch("optimize.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ code: code, mode: 'optimize' })
        });
        
        const data = await response.json();
        document.getElementById("outputCode").value = data.optimized_code;
        updateCharts(window.originalMetrics, data.metrics);
        
        document.getElementById("optimizeBtn").innerText = "2. Optimize & Compare";
        document.getElementById("downloadBtn").style.display = "inline-block";
    } catch (err) {
        alert("Optimization Failed.");
        document.getElementById("optimizeBtn").innerText = "2. Optimize & Compare";
    }
}

function downloadPDF() {
    const element = document.getElementById('reportContainer');
    const opt = {
      margin:       0.5,
      filename:     'Green_Code_Energy_Report.pdf',
      image:        { type: 'jpeg', quality: 0.98 },
      html2canvas:  { scale: 2, backgroundColor: '#1e1e1e' },
      jsPDF:        { unit: 'in', format: 'letter', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
}