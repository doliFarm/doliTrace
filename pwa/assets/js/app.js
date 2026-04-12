/**
 * DoliTrace PWA - Logic Core
 * Version: 3.0.0 (Orange Theme + Robust Scanner)
 */

const { createApp, ref, onMounted } = Vue;
// Mappatura Icone Agroecologiche (FontAwesome + Bootstrap Colors)
const ECO_ICONS = {
    cropplan:   { icon: 'fa-solid fa-calendar-days', class: 'text-success' },      // Piani (Verde)
    harvest:    { icon: 'fa-solid fa-basket-shopping', class: 'text-warning' },    // Raccolta (Arancione/Giallo)
    operation:  { icon: 'fa-solid fa-trowel', class: 'text-secondary' },           // Operazioni (Terra/Grigio)
    logbook:    { icon: 'fa-solid fa-book-open-reader', class: 'text-primary' },   // Quaderno (Blu)
    plot:       { icon: 'fa-solid fa-layer-group', class: 'text-success' },        // Appezzamento (Verde)
    machine:    { icon: 'fa-solid fa-tractor', class: 'text-warning' },            // Macchine (Giallo)
    drug:       { icon: 'fa-solid fa-spray-can-sparkles', class: 'text-info' },    // Trattamenti Bio (Azzurro)
    audit:      { icon: 'fa-solid fa-clipboard-check', class: 'text-success' }     // Audit (Verde scuro)
};

// Funzione helper da usare nel template HTML
const getIconClass = (type) => {
    const conf = ECO_ICONS[type] || { icon: 'fa-circle', class: 'text-muted' };
    return `${conf.icon} ${conf.class}`;
};

createApp({
    setup() {
        // --- STATE ---
        const currentView = ref('login');
        const isScanning = ref(false);
        const html5QrcodeScanner = ref(null);
        
        // Auth
        const user = ref(null);
        const loginForm = ref({ username: '', password: '' });
        const errorMsg = ref('');
        const isLoading = ref(false);

        // Data
        const plots = ref([]);
        const plans = ref([]);
        const operationsTypes = ref([]);
        const actionQueue = ref([]);
        
        // Context
        const activePlot = ref(null);
        const activePlan = ref(null);
        const formData = ref({});

        onMounted(() => {
            const storedUser = localStorage.getItem('dt_user');
            if (storedUser) {
                user.value = JSON.parse(storedUser);
                loadMockData(user.value.id); 
                loadQueueFromStorage();
                currentView.value = 'home';
            }
        });

        // --- AUTH ---
        const doLogin = () => {
            isLoading.value = true;
            errorMsg.value = '';
            
            setTimeout(() => {
                let mockUser = null;
                if (loginForm.value.username === 'demo') {
                    mockUser = { id: 1, name: 'Mario Rossi (Demo)', token: 'u1' };
                } else if (loginForm.value.username === 'luigi') {
                    mockUser = { id: 2, name: 'Luigi Verdi', token: 'u2' };
                }

                if (mockUser) {
                    localStorage.setItem('dt_user', JSON.stringify(mockUser));
                    user.value = mockUser;
                    loadMockData(mockUser.id);
                    loadQueueFromStorage();
                    currentView.value = 'home';
                } else {
                    errorMsg.value = "Utente non trovato (Usa: demo / demo)";
                }
                isLoading.value = false;
            }, 800);
        };

        const logout = () => {
            localStorage.removeItem('dt_user');
            user.value = null;
            plans.value = [];
            plots.value = [];
            currentView.value = 'login';
        };

        // --- DATA LOAD (Mock Native Contacts) ---
        const loadMockData = (userId) => {
            const allPlots = [
                { uuid: '550e8400-e29b-41d4-a716-446655440000', label: 'Settore A - Serre', size: '1.5 Ha' },
                { uuid: '123e4567-e89b-12d3-a456-426614174000', label: 'Settore B - Campo Aperto', size: '3.0 Ha' }
            ];

            const allPlans = [
                { 
                    id: 100, 
                    fk_plot_uuid: '550e8400-e29b-41d4-a716-446655440000', 
                    crop: 'Pomodoro Ciliegino', 
                    variety: 'Pixel', 
                    start_date: '2026-01-15',
                    linked_contacts: [1] 
                },
                { 
                    id: 101, 
                    fk_plot_uuid: '123e4567-e89b-12d3-a456-426614174000', 
                    crop: 'Zucchina', 
                    variety: 'Romanesca', 
                    start_date: '2026-02-01',
                    linked_contacts: [1, 2] 
                }
            ];

            plans.value = allPlans.filter(p => p.linked_contacts.includes(userId));
            const visiblePlotUUIDs = plans.value.map(p => p.fk_plot_uuid);
            plots.value = allPlots.filter(p => visiblePlotUUIDs.includes(p.uuid));

            operationsTypes.value = [
                { id: 1, label: 'Irrigazione' },
                { id: 2, label: 'Trattamento Fito' },
                { id: 3, label: 'Concimazione' },
                { id: 4, label: 'Sarchiatura' }
            ];
        };

        // --- SCANNER ---
        const startScan = () => {
            isScanning.value = true;
            // Delay per il DOM
            setTimeout(() => {
                const onScanSuccess = (decodedText) => {
                    stopScan();
                    handleQrCode(decodedText);
                };
                
                const html5QrCode = new Html5Qrcode("qr-reader");
                html5QrCode.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    onScanSuccess,
                    () => {}
                ).catch(err => {
                    alert("Errore Camera: " + err);
                    isScanning.value = false;
                });
                
                html5QrcodeScanner.value = html5QrCode;
            }, 300);
        };

        const stopScan = () => {
            if (html5QrcodeScanner.value) {
                html5QrcodeScanner.value.stop().then(() => {
                    html5QrcodeScanner.value.clear();
                    isScanning.value = false;
                }).catch(() => { isScanning.value = false; });
            } else { isScanning.value = false; }
        };

        // --- LOGIC ---
        const handleQrCode = (uuid) => {
            console.log("QR:", uuid);
            const plot = plots.value.find(p => p.uuid === uuid);
            
            if (!plot) {
                alert("QR Sconosciuto o Accesso Negato per questo utente.");
                return;
            }

            const plan = plans.value.find(p => p.fk_plot_uuid === plot.uuid);
            if (!plan) {
                alert("Nessun piano attivo su questo plot.");
                return;
            }

            activePlot.value = plot;
            activePlan.value = plan;
            currentView.value = 'details';
        };

        const selectPlanManually = (plan) => {
            activePlan.value = plan;
            activePlot.value = plots.value.find(p => p.uuid === plan.fk_plot_uuid);
            currentView.value = 'details';
        };

        const getPlotName = (uuid) => {
            const p = plots.value.find(plot => plot.uuid === uuid);
            return p ? p.label : '...';
        };

        // --- FORMS & SYNC ---
        const openForm = (type) => {
            formData.value = {
                type: type, 
                date: new Date().toISOString().slice(0,10),
                qty: null,
                op_type: null,
                note: ''
            };
            currentView.value = type === 'harvest' ? 'form_harvest' : 'form_op';
        };

        const saveAction = () => {
            const newRecord = {
                uid: Date.now(),
                fk_plan_id: activePlan.value.id,
                user_id: user.value.id,
                ...formData.value,
                timestamp: new Date().toISOString(),
                synced: false
            };

            actionQueue.value.push(newRecord);
            saveQueueToStorage();
            
            alert("✅ Salvato in locale (Offline).");
            currentView.value = 'details';
        };

        const loadQueueFromStorage = () => {
            const stored = localStorage.getItem('dt_queue');
            if (stored) actionQueue.value = JSON.parse(stored);
        };

        const saveQueueToStorage = () => {
            localStorage.setItem('dt_queue', JSON.stringify(actionQueue.value));
        };

        const syncData = () => {
            if (actionQueue.value.length === 0) {
                alert("Nessun dato da inviare.");
                return;
            }
            if (!confirm(`Inviare ${actionQueue.value.length} elementi?`)) return;

            setTimeout(() => {
                console.log("SYNC:", actionQueue.value);
                actionQueue.value = []; 
                saveQueueToStorage();
                alert("Upload completato!");
            }, 1000);
        };

        return {
            currentView, isScanning, isLoading, errorMsg, loginForm,
            user, plots, plans, operationsTypes, actionQueue,
            activePlot, activePlan, formData,
            doLogin, logout, startScan, stopScan, selectPlanManually, getPlotName,
            openForm, saveAction, syncData
        };
    }
}).mount('#app');