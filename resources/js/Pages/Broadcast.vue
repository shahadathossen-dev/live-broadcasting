<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';
import Peer from "simple-peer";
import { getPermissions } from "../utils";
import { ref, reactive } from 'vue';
const page = usePage()
const authUser = page.props.auth.user;
const video = ref(null);
const isVisibleLink = ref(false);
const streamingUsers = ref([]);
const allPeers = reactive({});
const currentlyConnectedUser = ref(null);
const streamId = ref(Math.random().toString(36).substring(2,10));
// Echo.private(`App.Models.User.${page.props.auth.user.id}`)
//     .listen('OrderShipmentStatusUpdated', (e) => {
//         console.log(e.order);
//     });
// Echo.channel(`notifications.${page.props.auth.user.id}`)
//     .notification((data) => {
//         console.log(data);
//     });

const startStream = async() => {
    // microphone and camera permissions
    const stream = await getPermissions();
    video.value.srcObject = stream;
    initializeStreamingChannel();
    initializeSignalAnswerChannel(); // a private channel where the broadcaster listens to incoming signalling answer
    isVisibleLink.value = true;
};
const initializeStreamingChannel = () => {
Echo.join(`streaming-channel.${streamId.value}`)
    .here((users) => {
        streamingUsers.value = users;
    })
    .joining((user) => {
        console.log("New User", user);
        // if this new user is not already on the call, send your stream offer
        const joiningUserIndex = streamingUsers.value.findIndex(
            (data) => data.id === user.id
        );
        if (joiningUserIndex < 0) {
            streamingUsers.value.push(user);
            // A new user just joined the channel so signal that user
            currentlyConnectedUser.value = user.id;
            allPeers[user.id] = peerCreator(
                video.value.srcObject,
                user,
                signalCallback
            )
            // Create Peer
            allPeers[user.id].create();
            // Initialize Events
            allPeers[user.id].initEvents();
        }
    })
    .leaving((user) => {
        console.log(user.name, "Left");
        // destroy peer
        allPeers[user.id].getPeer().destroy();
        // delete peer object
        delete allPeers[user.id];
        // if one leaving is the broadcaster set streamingUsers to empty array
        if (user.id === authUser.id) {
            streamingUsers.value = [];
        } else {
            // remove from streamingUsers array
            const leavingUserIndex = streamingUsers.findIndex(
            (data) => data.id === user.id
            );
            streamingUsers.value.splice(leavingUserIndex, 1);
        }
    });
    // streamingPresenceChannel.here((users) => {
    //     this.streamingUsers = users;
    // });

    // streamingPresenceChannel.joining((user) => {
    //     console.log("New User", user);
    //     // if this new user is not already on the call, send your stream offer
    //     const joiningUserIndex = this.streamingUsers.findIndex(
    //         (data) => data.id === user.id
    //     );
    //     if (joiningUserIndex < 0) {
    //         this.streamingUsers.push(user);
    //         // A new user just joined the channel so signal that user
    //         this.currentlyContactedUser = user.id;
    //         this.$set(
    //         this.allPeers,
    //         `${user.id}`,
    //         this.peerCreator(
    //             this.$refs.broadcaster.srcObject,
    //             user,
    //             this.signalCallback
    //         )
    //         );
    //         // Create Peer
    //         this.allPeers[user.id].create();
    //         // Initialize Events
    //         this.allPeers[user.id].initEvents();
    //     }
    // });
    // streamingPresenceChannel.leaving((user) => {
    //     console.log(user.name, "Left");
    //     // destroy peer
    //     this.allPeers[user.id].getPeer().destroy();
    //     // delete peer object
    //     delete this.allPeers[user.id];
    //     // if one leaving is the broadcaster set streamingUsers to empty array
    //     if (user.id === this.auth_user_id) {
    //         this.streamingUsers = [];
    //     } else {
    //         // remove from streamingUsers array
    //         const leavingUserIndex = this.streamingUsers.findIndex(
    //         (data) => data.id === user.id
    //         );
    //         this.streamingUsers.splice(leavingUserIndex, 1);
    //     }
    // });
};
const signalCallback = (offer, user) => {
    axios
    .post("/stream-offer", {
        broadcaster: authUser.id,
        receiver: user,
        offer,
    })
    .then((res) => {
        console.log(res);
    })
    .catch((err) => {
        console.log(err);
    });
};
const peerCreator = (stream, user, signalCallback) => {
    let peer;
    return {
    create: () => {
        peer = new Peer({
            initiator: true,
            trickle: false,
            stream: stream,
            config: {
                iceServers: [
                    {
                        urls: ["stun:stun.stunprotocol.org", "stun:stun1.l.google.com:19302"],
                    },
                    {
                        urls: [process.env.TURN_SERVER_HOST],
                        username: process.env.TURN_SERVER_USER,
                        credential: process.env.TURN_SERVER_CRED,
                    },
                ],
            },
        });
    },
    getPeer: () => peer,
    initEvents: () => {
        peer.on("signal", (data) => {
            // send offer over here.
            signalCallback(data, user);
        });
        peer.on("stream", (stream) => {
            console.log("onStream");
        });
        peer.on("track", (track, stream) => {
            console.log("onTrack");
        });
        peer.on("connect", () => {
            console.log("Broadcaster Peer connected");
        });
        peer.on("close", () => {
            console.log("Broadcaster Peer closed");
        });
        peer.on("error", (err) => {
            console.log("handle error gracefully");
        });
    },
    };
}
const initializeSignalAnswerChannel = () => {
Echo.private(`stream-signal-channel.${authUser.id}`)
    .listen("StreamAnswer", ({ data }) => {
            console.log("Signal Answer from private channel");
        if (data.answer.renegotiate) {
            console.log("renegotating");
        }
        if (data.answer.sdp) {
            const updatedSignal = {
                ...data.answer,
                sdp: `${data.answer.sdp}\n`,
            };
            allPeers[currentlyConnectedUser.value]
                .getPeer()
                .signal(updatedSignal);
            }
        }
    );
    // window.Echo.private(`stream-signal-channel.${this.auth_user_id}`)
    //     .listen("StreamAnswer", ({ data }) => {
    //             console.log("Signal Answer from private channel");
    //         if (data.answer.renegotiate) {
    //             console.log("renegotating");
    //         }
    //         if (data.answer.sdp) {
    //             const updatedSignal = {
    //                 ...data.answer,
    //                 sdp: `${data.answer.sdp}\n`,
    //             };
    //             this.allPeers[this.currentlyContactedUser]
    //                 .getPeer()
    //                 .signal(updatedSignal);
    //             }
    //         }
    //     );
};


</script>

<template>
    <AppLayout title="Stream">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Stream
            </h2>
        </template>

        <div class="py-12">
            <button @click="startStream" class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Start</button>
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <video ref="video"></video>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
