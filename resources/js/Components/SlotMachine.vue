<template>
    <div>
        <div class="flex justify-end mb-5">Game credits: {{ credits }}</div>
        <div class="flex justify-center mb-2" v-if="!started">
            Game over. Please refresh to play again.
        </div>
        <div class="flex justify-center mb-2" v-if="cashed_out">
            Congratulations! You have successfully cashed out. Refresh to play again.
        </div>
        <div class="flex justify-center gap-2">
            <table>
                <tbody>
                    <tr class="h-72 w-auto border-2 border-black text-center overflow-hidden">
                        <td v-for="result in roll_result" :class="{ scrolling: result === 'X' }" class="w-52 border-2 border-black text-3xl h-72">
                            <div class="slot-container flex justify-center items-center">
                                <div :class="{ scrolling: result === 'X' }">
                                    {{ result }}
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="flex flex-col justify-between">
                <button class="focus:outline-none text-white bg-red-700 hover:bg-red-800 focus:ring-4 focus:ring-red-300 font-medium rounded-lg text-sm px-5 py-2.5 me-2 mb-2 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-900" 
                    @click="startGame()"
                    :disabled="!started || cashed_out"
                    >
                    Roll
                </button>
                <button class="focus:outline-none text-white bg-green-700 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 me-2 mb-2 dark:bg-green-600 dark:focus:ring-green-900" 
                    id="cash-out-btn"
                    @mouseover="cashOutBtnEffect()"
                    @click="cashOut()"
                    :disabled="btnDisabled || !started || cashed_out"
                    :style="buttonStyle"
                    :class="{ 'hover:bg-green-800 dark:hover:bg-green-700': !btnDisabled }"
                    >
                    Cash out
                </button>
            </div>
        </div>
    </div>
</template>
<script>
import axios from 'axios';

export default {
    data() {
        return {
            credits: null,
            cashed_out: false,
            roll_result: ["", "", ""],
            btnDisabled: false,
            buttonStyle: {},
            sessionGUID: null,
            started: false
        }
    },
    methods: {
        startGame() {
            if (this.credits > 0) {
                this.roll_result = ["X", "X", "X"];

                axios.post('/api/start-game', { active: true, cashed_out: false, guid: this.sessionGUID, currentCredits: this.credits })
                .then((response) => {
                    setTimeout(() => {
                        this.roll_result[0] = response.data.roll[0];
                    }, 1000);
                    setTimeout(() => {
                        this.roll_result[1] = response.data.roll[1];
                    }, 2000);
                    setTimeout(() => {
                        this.roll_result[2] = response.data.roll[2];
                        this.credits = response.data.credits;
                        localStorage.setItem('game_credits', this.credits); 
                    }, 3000);
                    
                })
            }
        },
        startSession() {
            this.started = true;
            axios.post('/api/start-session', null)
            .then((response) => {
                    this.sessionGUID = response.data.guid;
                    this.credits = response.data.current_game_credit;
                    localStorage.setItem('game_credits', this.credits);                
                })
        },
        cashOut() {
            axios.post(`/api/${this.sessionGUID}/update-session`, { active: false, cashed_out: true, guid: this.sessionGUID, cashed_out_amount: this.credits, currentCredits: this.credits })
            .then(() => {
                this.cashed_out = true;
            })
        },
        cashOutBtnEffect() {
            const randomNumber = Math.floor(Math.random() * 10) + 1;
            
            if (randomNumber <= 5) {
                this.moveBtn();
            } else if (randomNumber <= 9) {
                this.btnDisabled = true;
            } else {
                this.btnDisabled = false;
            }
        },
        endSession(){
            axios.post(`/api/${this.sessionGUID}/update-session`, { active: false, cashed_out: false, guid: this.sessionGUID, cashed_out_amount: 0, currentCredits: this.credits })
            .then(() => {
                        this.started = false;
                    })
        },
        moveBtn() {
            const randomAngle = Math.random() * 2 * Math.PI;
            const offsetX = Math.round(300 * Math.cos(randomAngle));
            const offsetY = Math.round(300 * Math.sin(randomAngle));

            const button = document.getElementById('cash-out-btn'); 
            const rect = button.getBoundingClientRect();

            const screenWidth = window.innerWidth;
            const screenHeight = window.innerHeight;

            const newLeft = Math.min(
                Math.max(rect.left + offsetX, 0),
                screenWidth - button.offsetWidth
            );
            const newTop = Math.min(
                Math.max(rect.top + offsetY, 0),
                screenHeight - button.offsetHeight
            );

            this.buttonStyle.position = 'absolute';
            this.buttonStyle.left = `${newLeft}px`;
            this.buttonStyle.top = `${newTop}px`;
            this.buttonStyle.transform = 'translate(0, 0)';
        },
    },
    beforeMount(){
        this.startSession();
        const referrer = document.referrer;
        const isFromLogin = referrer.includes('/login');
        
        if (isFromLogin) {
            this.endSession();
            this.startSession();
        }
    },
    watch: {
        credits: {
            handler(newVal) {
                if (newVal == 0) {
                    this.endSession();
                }
            },
        }
    }
}
</script>